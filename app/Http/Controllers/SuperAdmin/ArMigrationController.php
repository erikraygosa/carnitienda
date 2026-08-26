<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\ArMovement;
use App\Models\Client;
use App\Models\SalesOrder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class ArMigrationController extends Controller
{
    private const COLUMNAS = ['Cliente (nombre exacto)', 'Folio', 'Fecha (AAAA-MM-DD)', 'Total original', 'Saldo pendiente', 'Comentario'];

    public function index()
    {
        $migradas = SalesOrder::where('comentarios', 'like', 'Migrado%')
            ->with('client')
            ->latest('fecha')
            ->paginate(20);

        $totalMigrado = SalesOrder::where('comentarios', 'like', 'Migrado%')->sum('saldo_pendiente');

        $clientes = Client::where('activo', true)->orderBy('nombre')->get(['id', 'nombre']);

        return view('superadmin.ar-migration.index', compact('migradas', 'totalMigrado', 'clientes'));
    }

    /**
     * Alta directa de UNA cuenta por cobrar migrada, sin pasar por todo el
     * flujo de pedido → surtido → despacho. Crea de una vez:
     *  - el "pedido" (sales_orders) a crédito ya en ENTREGADO, que es lo que
     *    usa el reporte de Cobranza General y contra lo que se aplican los
     *    abonos cuando un chofer cobra en ruta.
     *  - el movimiento en ar_movements (CARGO), que es lo que se suma para
     *    saber cuánto se le asigna al chofer al armar un despacho.
     * Sin las dos, la migración queda incompleta: con solo el pedido, el
     * saldo no aparece al armar despachos; con solo el movimiento, el
     * saldo no aparece en Cobranza General ni se puede cobrar en ruta.
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'client_id'        => 'required|exists:clients,id',
            'folio'            => 'required|string|max:60|unique:sales_orders,folio',
            'fecha'            => 'required|date',
            'total'            => 'required|numeric|min:0.01',
            'saldo_pendiente'  => 'required|numeric|min:0|lte:total',
            'comentario'       => 'nullable|string|max:255',
        ]);

        $this->crearCxcMigrada($data);

        session()->flash('swal', ['icon' => 'success', 'title' => 'CxC migrada agregada']);
        return redirect()->route('superadmin.ar-migration.index');
    }

    private function crearCxcMigrada(array $data): SalesOrder
    {
        return DB::transaction(function () use ($data) {
            $comentario = 'Migrado desde sistema anterior' . ($data['comentario'] ?? '' ? ' — ' . $data['comentario'] : '');

            $order = SalesOrder::create([
                'client_id'       => $data['client_id'],
                'folio'           => $data['folio'],
                'fecha'           => $data['fecha'],
                'delivery_type'   => 'ENVIO',
                'payment_method'  => SalesOrder::PM_CREDITO,
                'moneda'          => 'MXN',
                'subtotal'        => $data['total'],
                'impuestos'       => 0,
                'descuento'       => 0,
                'total'           => $data['total'],
                'saldo_pendiente' => $data['saldo_pendiente'],
                'status'          => SalesOrder::S_ENTREGADO,
                'entregado_at'    => $data['fecha'],
                'created_by'      => auth()->id(),
                'comentarios'     => $comentario,
            ]);

            // El CARGO se registra por el saldo pendiente (no el total original):
            // es lo único relevante para calcular cuánto se le asigna a un
            // chofer al armar un despacho — si ya se había abonado algo en el
            // sistema viejo, aquí solo entra lo que de verdad falta por cobrar.
            if ((float) $data['saldo_pendiente'] > 0) {
                ArMovement::create([
                    'client_id'   => $data['client_id'],
                    'fecha'       => $data['fecha'],
                    'tipo'        => 'CARGO',
                    'monto'       => $data['saldo_pendiente'],
                    'descripcion' => $comentario . ' (folio ' . $data['folio'] . ')',
                    'source_type' => SalesOrder::class,
                    'source_id'   => $order->id,
                    'created_by'  => auth()->id(),
                ]);
            }

            return $order;
        });
    }

    public function plantilla()
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('CxC migradas');

        foreach (self::COLUMNAS as $i => $header) {
            $coord = $this->col($i + 1) . '1';
            $sheet->setCellValue($coord, $header);
            $sheet->getStyle($coord)->applyFromArray([
                'font'      => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '4F46E5']],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
            ]);
            $sheet->getColumnDimension($this->col($i + 1))->setWidth(24);
        }

        // Fila de ejemplo, para que quede claro el formato
        $sheet->fromArray(['Juan Pérez', 'MIG-0001', now()->format('Y-m-d'), 1500, 1500, 'Saldo del sistema anterior'], null, 'A2');
        $sheet->getStyle('A2:F2')->getFont()->setItalic(true);

        $tmpPath = tempnam(sys_get_temp_dir(), 'cxc') . '.xlsx';
        (new Xlsx($spreadsheet))->save($tmpPath);

        return response()->download($tmpPath, 'plantilla-cxc-migradas.xlsx')->deleteFileAfterSend(true);
    }

    public function importar(Request $request)
    {
        $request->validate([
            'archivo' => ['required', 'file', 'mimes:xlsx,xls,csv'],
        ]);

        $spreadsheet = IOFactory::load($request->file('archivo')->getRealPath());
        $filas = $spreadsheet->getActiveSheet()->toArray(null, true, true, false);
        array_shift($filas); // encabezados

        $creadas = 0;
        $errores = [];

        foreach ($filas as $i => $fila) {
            $numFila = $i + 2;
            [$nombreCliente, $folio, $fecha, $total, $saldo, $comentario] = array_pad($fila, 6, null);

            $nombreCliente = trim((string) $nombreCliente);
            $folio         = trim((string) $folio);

            if ($nombreCliente === '' && $folio === '') {
                continue; // fila vacía
            }

            if ($nombreCliente === '') {
                $errores[] = "Fila {$numFila}: falta el nombre del cliente.";
                continue;
            }

            $client = Client::whereRaw('LOWER(nombre) = ?', [mb_strtolower($nombreCliente)])->first();
            if (! $client) {
                $errores[] = "Fila {$numFila}: cliente \"{$nombreCliente}\" no encontrado (nombre debe coincidir exacto con el registrado).";
                continue;
            }

            if ($folio === '') {
                $errores[] = "Fila {$numFila}: falta el folio.";
                continue;
            }

            if (SalesOrder::where('folio', $folio)->exists()) {
                $errores[] = "Fila {$numFila}: el folio \"{$folio}\" ya existe, se omitió.";
                continue;
            }

            if (! is_numeric($total) || (float) $total <= 0) {
                $errores[] = "Fila {$numFila}: total inválido.";
                continue;
            }

            $saldo = is_numeric($saldo) ? (float) $saldo : (float) $total;
            if ($saldo > (float) $total) {
                $errores[] = "Fila {$numFila}: el saldo pendiente no puede ser mayor al total, se omitió.";
                continue;
            }

            $fechaParsed = null;
            try {
                $fechaParsed = \Carbon\Carbon::parse($fecha ?: now())->format('Y-m-d');
            } catch (\Throwable $e) {
                $errores[] = "Fila {$numFila}: fecha inválida \"{$fecha}\".";
                continue;
            }

            $this->crearCxcMigrada([
                'client_id'       => $client->id,
                'folio'           => $folio,
                'fecha'           => $fechaParsed,
                'total'           => (float) $total,
                'saldo_pendiente' => $saldo,
                'comentario'      => $comentario ? trim((string) $comentario) : null,
            ]);
            $creadas++;
        }

        return back()->with('resultado_importacion_cxc', [
            'creadas' => $creadas,
            'errores' => $errores,
        ]);
    }

    private function col(int $n): string
    {
        return Coordinate::stringFromColumnIndex($n);
    }
}
