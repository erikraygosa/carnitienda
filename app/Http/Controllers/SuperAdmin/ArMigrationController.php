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
    // Serie de folios exclusiva para las CxC migradas, para no chocar ni
    // mezclarse con los folios reales de pedidos (SO-...) del sistema.
    private const FOLIO_PREFIX = 'MIG-';

    private const COLUMNAS = ['Cliente (nombre exacto)', 'Folio/Referencia del sistema anterior (opcional)', 'Fecha (AAAA-MM-DD)', 'Total original', 'Saldo pendiente', 'Comentario'];

    public function index()
    {
        $migradas = SalesOrder::where('folio', 'like', self::FOLIO_PREFIX . '%')
            ->with('client')
            ->latest('fecha')
            ->paginate(20);

        $totalMigrado = SalesOrder::where('folio', 'like', self::FOLIO_PREFIX . '%')->sum('saldo_pendiente');

        $clientes = Client::where('activo', true)->orderBy('nombre')->get(['id', 'nombre']);

        $siguienteFolio = $this->siguienteFolio();

        return view('superadmin.ar-migration.index', compact('migradas', 'totalMigrado', 'clientes', 'siguienteFolio'));
    }

    /**
     * Siguiente folio disponible de la serie MIG-####, buscando el
     * consecutivo más alto ya usado.
     */
    private function siguienteFolio(): string
    {
        $ultimo = SalesOrder::where('folio', 'like', self::FOLIO_PREFIX . '%')
            ->orderByRaw('CAST(SUBSTRING(folio, ?) AS UNSIGNED) DESC', [strlen(self::FOLIO_PREFIX) + 1])
            ->value('folio');

        $numero = $ultimo ? ((int) substr($ultimo, strlen(self::FOLIO_PREFIX))) + 1 : 1;

        return self::FOLIO_PREFIX . str_pad((string) $numero, 4, '0', STR_PAD_LEFT);
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
            'referencia'       => 'nullable|string|max:60',
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
            $folio = $data['folio'] ?? $this->siguienteFolio();

            $partesComentario = ['Migrado desde sistema anterior'];
            if (! empty($data['referencia'])) $partesComentario[] = 'Folio/ref. anterior: ' . $data['referencia'];
            if (! empty($data['comentario'])) $partesComentario[] = $data['comentario'];
            $comentario = implode(' — ', $partesComentario);

            $order = SalesOrder::create([
                'client_id'       => $data['client_id'],
                'folio'           => $folio,
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
                    'descripcion' => $comentario . ' (folio ' . $folio . ')',
                    'source_type' => SalesOrder::class,
                    'source_id'   => $order->id,
                    'created_by'  => auth()->id(),
                ]);
            }

            return $order;
        });
    }

    /**
     * Solo dejamos editar/borrar mientras nadie le ha aplicado un cobro
     * todavía (saldo_pendiente == total, tal como se creó). Si ya se le
     * cobró algo en ruta, tocarla aquí desde SuperAdmin dejaría descuadrado
     * el ar_movements / dispatch_ar_assignments de esa cobranza real — a
     * partir de ahí ya se maneja como cualquier otra CxC, desde Cobranza.
     */
    private function puedeEditarse(SalesOrder $order): bool
    {
        return str_starts_with($order->folio, self::FOLIO_PREFIX)
            && abs((float) $order->total - (float) $order->saldo_pendiente) < 0.005;
    }

    public function edit(SalesOrder $order)
    {
        abort_unless(str_starts_with($order->folio, self::FOLIO_PREFIX), 404);

        if (! $this->puedeEditarse($order)) {
            session()->flash('swal', ['icon' => 'error', 'title' => 'No se puede editar', 'text' => 'Esta CxC ya tiene abonos aplicados — edítala desde Cobranza General.']);
            return redirect()->route('superadmin.ar-migration.index');
        }

        $clientes = Client::where('activo', true)->orderBy('nombre')->get(['id', 'nombre']);

        return view('superadmin.ar-migration.edit', compact('order', 'clientes'));
    }

    public function update(Request $request, SalesOrder $order)
    {
        abort_unless(str_starts_with($order->folio, self::FOLIO_PREFIX), 404);

        if (! $this->puedeEditarse($order)) {
            session()->flash('swal', ['icon' => 'error', 'title' => 'No se puede editar', 'text' => 'Esta CxC ya tiene abonos aplicados.']);
            return redirect()->route('superadmin.ar-migration.index');
        }

        $data = $request->validate([
            'client_id'        => 'required|exists:clients,id',
            'referencia'       => 'nullable|string|max:60',
            'fecha'            => 'required|date',
            'total'            => 'required|numeric|min:0.01',
            'saldo_pendiente'  => 'required|numeric|min:0|lte:total',
            'comentario'       => 'nullable|string|max:255',
        ]);

        DB::transaction(function () use ($data, $order) {
            $partesComentario = ['Migrado desde sistema anterior'];
            if (! empty($data['referencia'])) $partesComentario[] = 'Folio/ref. anterior: ' . $data['referencia'];
            if (! empty($data['comentario'])) $partesComentario[] = $data['comentario'];
            $comentario = implode(' — ', $partesComentario);

            $order->update([
                'client_id'       => $data['client_id'],
                'fecha'           => $data['fecha'],
                'entregado_at'    => $data['fecha'],
                'subtotal'        => $data['total'],
                'total'           => $data['total'],
                'saldo_pendiente' => $data['saldo_pendiente'],
                'comentarios'     => $comentario,
            ]);

            // El ar_movement viejo ya no aplica (cambió cliente y/o monto) —
            // se recrea desde cero en vez de intentar actualizarlo a medias.
            ArMovement::where('source_type', SalesOrder::class)->where('source_id', $order->id)->delete();

            if ((float) $data['saldo_pendiente'] > 0) {
                ArMovement::create([
                    'client_id'   => $data['client_id'],
                    'fecha'       => $data['fecha'],
                    'tipo'        => 'CARGO',
                    'monto'       => $data['saldo_pendiente'],
                    'descripcion' => $comentario . ' (folio ' . $order->folio . ')',
                    'source_type' => SalesOrder::class,
                    'source_id'   => $order->id,
                    'created_by'  => auth()->id(),
                ]);
            }
        });

        session()->flash('swal', ['icon' => 'success', 'title' => 'CxC actualizada']);
        return redirect()->route('superadmin.ar-migration.index');
    }

    public function destroy(SalesOrder $order)
    {
        abort_unless(str_starts_with($order->folio, self::FOLIO_PREFIX), 404);

        if (! $this->puedeEditarse($order)) {
            session()->flash('swal', ['icon' => 'error', 'title' => 'No se puede eliminar', 'text' => 'Esta CxC ya tiene abonos aplicados.']);
            return redirect()->route('superadmin.ar-migration.index');
        }

        DB::transaction(function () use ($order) {
            ArMovement::where('source_type', SalesOrder::class)->where('source_id', $order->id)->delete();
            $order->delete();
        });

        session()->flash('swal', ['icon' => 'success', 'title' => 'CxC eliminada']);
        return redirect()->route('superadmin.ar-migration.index');
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
            $sheet->getColumnDimension($this->col($i + 1))->setWidth(26);
        }

        // Fila de ejemplo, para que quede claro el formato
        $sheet->fromArray(['Juan Pérez', 'FACT-00123 (sistema viejo)', now()->format('Y-m-d'), 1500, 1500, 'Saldo del sistema anterior'], null, 'A2');
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

        // Consecutivo local para toda la tanda — evita repetir folio entre
        // filas del mismo archivo (si consultáramos la BD en cada vuelta,
        // dos filas seguidas podrían calcular el mismo "siguiente" folio
        // porque la anterior aún no se ha guardado al momento de calcularlo).
        $siguienteNumero = (int) substr($this->siguienteFolio(), strlen(self::FOLIO_PREFIX));

        foreach ($filas as $i => $fila) {
            $numFila = $i + 2;
            [$nombreCliente, $referencia, $fecha, $total, $saldo, $comentario] = array_pad($fila, 6, null);

            $nombreCliente = trim((string) $nombreCliente);
            $referencia    = trim((string) $referencia);

            if ($nombreCliente === '') {
                continue; // fila vacía
            }

            $client = Client::whereRaw('LOWER(nombre) = ?', [mb_strtolower($nombreCliente)])->first();
            if (! $client) {
                $errores[] = "Fila {$numFila}: cliente \"{$nombreCliente}\" no encontrado (nombre debe coincidir exacto con el registrado).";
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

            try {
                $fechaParsed = \Carbon\Carbon::parse($fecha ?: now())->format('Y-m-d');
            } catch (\Throwable $e) {
                $errores[] = "Fila {$numFila}: fecha inválida \"{$fecha}\".";
                continue;
            }

            $folio = self::FOLIO_PREFIX . str_pad((string) $siguienteNumero, 4, '0', STR_PAD_LEFT);
            $siguienteNumero++;

            $this->crearCxcMigrada([
                'client_id'       => $client->id,
                'folio'           => $folio,
                'referencia'      => $referencia ?: null,
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
