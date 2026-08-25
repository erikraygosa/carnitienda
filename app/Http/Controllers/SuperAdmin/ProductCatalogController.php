<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\Request;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class ProductCatalogController extends Controller
{
    /**
     * Columnas del catálogo: [encabezado, campo en el modelo]
     * El orden aquí es el orden en el Excel (import y export deben coincidir).
     */
    private const COLUMNAS = [
        ['ID',                    'id'],
        ['SKU',                   'sku'],
        ['Nombre',                'nombre'],
        ['Categoría',             'categoria'],       // solo lectura, no se importa
        ['Unidad (texto)',        'unidad'],
        ['Precio base',           'precio_base'],
        ['Activo (Si/No)',        'activo'],
        ['Código de barras',      'barcode'],
        ['SAT Clave Prod/Serv',   'sat_clave_prod_serv'],
        ['SAT Clave Unidad',      'sat_clave_unidad'],
        ['SAT Objeto Imp',        'sat_objeto_imp'],
        ['SAT Tipo Factor',       'sat_tipo_factor'],
        ['SAT Tasa IVA',          'sat_tasa_iva'],
        ['SAT Tasa IEPS',         'sat_tasa_ieps'],
        ['SAT No Identificación', 'sat_no_identificacion'],
    ];

    private const SOLO_LECTURA = ['id', 'categoria'];

    public function index()
    {
        $totalProductos      = Product::count();
        $sinClaveProdServ    = Product::whereNull('sat_clave_prod_serv')->orWhere('sat_clave_prod_serv', '')->count();
        $sinClaveUnidad      = Product::whereNull('sat_clave_unidad')->orWhere('sat_clave_unidad', '')->count();

        return view('superadmin.products.index', compact('totalProductos', 'sinClaveProdServ', 'sinClaveUnidad'));
    }

    public function export()
    {
        $productos = Product::with('category')->orderBy('nombre')->get();

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Catalogo');

        foreach (self::COLUMNAS as $i => [$header, $campo]) {
            $coord = $this->col($i + 1) . '1';
            $sheet->setCellValue($coord, $header);
            $sheet->getStyle($coord)->applyFromArray([
                'font'      => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '4F46E5']],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
            ]);
        }

        foreach ($productos as $rowIdx => $p) {
            $rowNum = $rowIdx + 2;
            foreach (self::COLUMNAS as $colIdx => [$header, $campo]) {
                $valor = match ($campo) {
                    'categoria' => $p->category?->nombre ?? '',
                    'activo'    => $p->activo ? 'Si' : 'No',
                    default     => $p->{$campo},
                };
                $sheet->setCellValue($this->col($colIdx + 1) . $rowNum, $valor);
            }
        }

        foreach (range(1, count(self::COLUMNAS)) as $i) {
            $sheet->getColumnDimension($this->col($i))->setAutoSize(true);
        }
        $sheet->freezePane('A2');

        $filename = 'catalogo-productos-' . now()->format('Y-m-d_His') . '.xlsx';
        $tmpPath  = tempnam(sys_get_temp_dir(), 'cat') . '.xlsx';
        (new Xlsx($spreadsheet))->save($tmpPath);

        return response()->download($tmpPath, $filename)->deleteFileAfterSend(true);
    }

    public function importForm()
    {
        return view('superadmin.products.import');
    }

    public function import(Request $request)
    {
        $request->validate([
            'archivo' => ['required', 'file', 'mimes:xlsx,xls,csv'],
        ]);

        $spreadsheet = IOFactory::load($request->file('archivo')->getRealPath());
        $sheet       = $spreadsheet->getActiveSheet();
        $filas       = $sheet->toArray(null, true, true, false);

        // Mapear encabezados de la fila 1 a índice de columna, por si el usuario reordenó algo
        $encabezados = array_shift($filas);
        $mapaCampos  = [];
        foreach (self::COLUMNAS as [$header, $campo]) {
            $idx = array_search($header, $encabezados, true);
            if ($idx !== false) {
                $mapaCampos[$campo] = $idx;
            }
        }

        if (! isset($mapaCampos['id'])) {
            return back()->with('error', 'El archivo no tiene la columna "ID" — descarga la plantilla actual desde "Exportar catálogo" y no borres esa columna.');
        }

        $enumObjetoImp   = ['01', '02', '03'];
        $enumTipoFactor  = ['Tasa', 'Exento', 'Cuota'];

        $actualizados = 0;
        $sinCambios   = 0;
        $noEncontrados = [];
        $advertencias  = [];

        foreach ($filas as $numFila => $fila) {
            $filaExcel = $numFila + 2; // +1 por header, +1 porque el array empieza en 0

            $id = trim((string) ($fila[$mapaCampos['id']] ?? ''));
            if ($id === '') {
                continue; // fila vacía
            }

            $producto = Product::find($id);
            if (! $producto) {
                $noEncontrados[] = "Fila {$filaExcel}: ID {$id} no existe";
                continue;
            }

            $cambios = [];

            foreach (self::COLUMNAS as [$header, $campo]) {
                if (in_array($campo, self::SOLO_LECTURA, true) || ! isset($mapaCampos[$campo])) {
                    continue;
                }

                $valorCrudo = $fila[$mapaCampos[$campo]] ?? null;
                $valor = is_string($valorCrudo) ? trim($valorCrudo) : $valorCrudo;

                if ($campo === 'activo') {
                    if ($valor === '' || $valor === null) continue;
                    $cambios['activo'] = in_array(mb_strtolower((string) $valor), ['si', 'sí', '1', 'true', 'x'], true);
                    continue;
                }

                if ($campo === 'sat_objeto_imp') {
                    if ($valor === '' || $valor === null) continue;
                    if (! in_array($valor, $enumObjetoImp, true)) {
                        $advertencias[] = "Fila {$filaExcel}: SAT Objeto Imp '{$valor}' inválido (debe ser 01, 02 o 03) — se dejó sin cambiar";
                        continue;
                    }
                    $cambios['sat_objeto_imp'] = $valor;
                    continue;
                }

                if ($campo === 'sat_tipo_factor') {
                    if ($valor === '' || $valor === null) continue;
                    if (! in_array($valor, $enumTipoFactor, true)) {
                        $advertencias[] = "Fila {$filaExcel}: SAT Tipo Factor '{$valor}' inválido (debe ser Tasa, Exento o Cuota) — se dejó sin cambiar";
                        continue;
                    }
                    $cambios['sat_tipo_factor'] = $valor;
                    continue;
                }

                if (in_array($campo, ['precio_base', 'sat_tasa_iva', 'sat_tasa_ieps'], true)) {
                    if ($valor === '' || $valor === null) continue;
                    if (! is_numeric($valor)) {
                        $advertencias[] = "Fila {$filaExcel}: '{$header}' con valor '{$valor}' no es numérico — se dejó sin cambiar";
                        continue;
                    }
                    $cambios[$campo] = (float) $valor;
                    continue;
                }

                // Campos de texto libres: sku, nombre, unidad, barcode, sat_clave_prod_serv,
                // sat_clave_unidad, sat_no_identificacion
                if ($valor === null) continue;
                $cambios[$campo] = $valor === '' ? null : $valor;
            }

            if (empty($cambios)) {
                $sinCambios++;
                continue;
            }

            $producto->fill($cambios);
            if ($producto->isDirty()) {
                $producto->save();
                $actualizados++;
            } else {
                $sinCambios++;
            }
        }

        return back()->with('resultado_importacion', [
            'actualizados'   => $actualizados,
            'sin_cambios'    => $sinCambios,
            'no_encontrados' => $noEncontrados,
            'advertencias'   => $advertencias,
        ]);
    }

    private function col(int $n): string
    {
        return Coordinate::stringFromColumnIndex($n);
    }
}
