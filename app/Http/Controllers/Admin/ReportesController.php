<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SalesOrderItem;
use App\Models\SalesOrder;
use App\Models\SaleItem;
use App\Models\Sale;
use App\Models\DispatchItem;
use App\Models\ShippingRoute;
use App\Models\Driver;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Font;

class ReportesController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('can:ver reporte notas de venta',     only: ['notasDeVenta', 'notasDeVentaData', 'notasDeVentaExport']),
            new Middleware('can:ver reporte ventas por producto', only: ['ventasPorProducto', 'ventasPorProductoData', 'ventasPorProductoExport']),
            new Middleware('can:ver reporte liquidaciones',       only: ['liquidaciones', 'liquidacionesData', 'liquidacionesExport', 'liquidacionesConcentrado']),
        ];
    }

    // ─── Helpers ──────────────────────────────────────────────────────────────

    private function buildNotasDeVentaQuery(Request $request)
    {
        $search     = $request->get('search', '');
        $tipoVenta  = $request->get('tipo_venta', '');
        $fechaDesde = $request->get('fecha_desde', '');
        $fechaHasta = $request->get('fecha_hasta', '');

        return SalesOrderItem::select(
                'sales_order_items.id',
                'sales_orders.folio',
                'sales_orders.fecha',
                'sales_orders.payment_method',
                'clients.nombre as cliente',
                'products.nombre as producto',
                'sales_order_items.cantidad',
                'sales_order_items.precio',
                'sales_order_items.total',
                'sales_order_items.descripcion'
            )
            ->join('sales_orders', 'sales_orders.id', '=', 'sales_order_items.sales_order_id')
            ->join('products',     'products.id',      '=', 'sales_order_items.product_id')
            ->leftJoin('clients',  'clients.id',       '=', 'sales_orders.client_id')
            ->when($search, fn($q) => $q->where(fn($inner) =>
                $inner->where('sales_orders.folio',  'like', "%$search%")
                      ->orWhere('clients.nombre',    'like', "%$search%")
                      ->orWhere('products.nombre',   'like', "%$search%")
            ))
            ->when($tipoVenta,  fn($q) => $q->where('sales_orders.payment_method', $tipoVenta))
            ->when($fechaDesde, fn($q) => $q->whereDate('sales_orders.fecha', '>=', $fechaDesde))
            ->when($fechaHasta, fn($q) => $q->whereDate('sales_orders.fecha', '<=', $fechaHasta));
    }

    private function buildVentasPorProductoQuery(Request $request)
    {
        $search     = $request->get('search', '');
        $fechaDesde = $request->get('fecha_desde', now()->startOfMonth()->toDateString());
        $fechaHasta = $request->get('fecha_hasta', now()->toDateString());

        return SalesOrderItem::select(
                'products.nombre as producto',
                DB::raw('SUM(sales_order_items.cantidad) as total_kg'),
                DB::raw('SUM(sales_order_items.total) as monto_total'),
                DB::raw('ROUND(AVG(sales_order_items.precio), 2) as precio_promedio'),
                DB::raw('COUNT(*) as total_piezas')
            )
            ->join('products',     'products.id',      '=', 'sales_order_items.product_id')
            ->join('sales_orders', 'sales_orders.id',  '=', 'sales_order_items.sales_order_id')
            ->when($search,     fn($q) => $q->where('products.nombre', 'like', "%$search%"))
            ->when($fechaDesde, fn($q) => $q->whereDate('sales_orders.fecha', '>=', $fechaDesde))
            ->when($fechaHasta, fn($q) => $q->whereDate('sales_orders.fecha', '<=', $fechaHasta))
            ->groupBy('sales_order_items.product_id', 'products.nombre');
    }

    private function buildLiquidacionesQuery(Request $request)
    {
        $fecha           = $request->get('fecha', now()->toDateString());
        $routeId         = $request->get('route_id', '');
        $driverId        = $request->get('driver_id', '');
        $soloSinLiquidar = $request->get('solo_sin_liquidar', '1') === '1';

        return DispatchItem::select(
                'sales_orders.folio',
                'clients.nombre as cliente_nombre',
                'shipping_routes.nombre as ruta_nombre',
                'drivers.nombre as chofer_nombre',
                'dispatches.fecha',
                'sales_orders.total',
                'sales_orders.driver_settlement_status',
                'sales_orders.status as order_status'
            )
            ->join('dispatches',      'dispatches.id',      '=', 'dispatch_items.dispatch_id')
            ->join('sales_orders',    'sales_orders.id',    '=', 'dispatch_items.sales_order_id')
            ->leftJoin('clients',         'clients.id',         '=', 'sales_orders.client_id')
            ->leftJoin('shipping_routes', 'shipping_routes.id', '=', 'dispatches.shipping_route_id')
            ->leftJoin('drivers',         'drivers.id',         '=', 'dispatches.driver_id')
            ->whereIn('dispatches.status', ['EN_RUTA', 'CERRADO', 'ENTREGADO'])
            ->when($fecha,           fn($q) => $q->whereDate('dispatches.fecha', $fecha))
            ->when($routeId,         fn($q) => $q->where('dispatches.shipping_route_id', $routeId))
            ->when($driverId,        fn($q) => $q->where('dispatches.driver_id', $driverId))
            ->when($soloSinLiquidar, fn($q) => $q->where('sales_orders.driver_settlement_status', 'PENDIENTE'))
            ->orderBy('shipping_routes.nombre')
            ->orderBy('dispatches.id');
    }

    private function orderStatusLabels(): array
    {
        return [
            'BORRADOR'     => 'Borrador',
            'APROBADO'     => 'Aprobado',
            'PREPARANDO'   => 'Preparando',
            'PROCESADO'    => 'Procesado',
            'DESPACHADO'   => 'Despachado',
            'EN_RUTA'      => 'En ruta',
            'ENTREGADO'    => 'Entregado',
            'NO_ENTREGADO' => 'No entregado',
            'CANCELADO'    => 'Cancelado',
        ];
    }

    private function orderStatusClasses(): array
    {
        return [
            'BORRADOR'     => 'bg-gray-100 text-gray-700',
            'APROBADO'     => 'bg-blue-100 text-blue-700',
            'PREPARANDO'   => 'bg-sky-100 text-sky-700',
            'PROCESADO'    => 'bg-amber-100 text-amber-700',
            'DESPACHADO'   => 'bg-amber-100 text-amber-700',
            'EN_RUTA'      => 'bg-violet-100 text-violet-700',
            'ENTREGADO'    => 'bg-emerald-100 text-emerald-700',
            'NO_ENTREGADO' => 'bg-orange-100 text-orange-700',
            'CANCELADO'    => 'bg-rose-100 text-rose-700',
        ];
    }

    private function pendientesPorDespachar()
    {
        return SalesOrder::whereIn('status', ['PROCESADO', 'DESPACHADO'])
            ->with(['client:id,nombre', 'route:id,nombre'])
            ->latest()
            ->limit(200)
            ->get(['id', 'folio', 'client_id', 'shipping_route_id', 'status', 'total', 'programado_para'])
            ->map(fn($o) => [
                'folio'   => $o->folio,
                'cliente' => $o->client?->nombre ?? '—',
                'ruta'    => $o->route?->nombre ?? '—',
                'total'   => number_format((float) $o->total, 2),
                'fecha'   => $o->programado_para ? \Carbon\Carbon::parse($o->programado_para)->format('d/m/Y') : '—',
                'url'     => route('admin.sales-orders.edit', $o->id),
            ])
            ->values();
    }

    private function col(int $n): string
    {
        return \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($n);
    }

    private function makeSpreadsheet(array $headers, array $rows, string $title): Spreadsheet
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle($title);

        // Header row
        foreach ($headers as $i => $header) {
            $coord = $this->col($i + 1) . '1';
            $sheet->setCellValue($coord, $header);
            $sheet->getStyle($coord)->applyFromArray([
                'font'      => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '4F46E5']],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
            ]);
        }

        // Data rows
        foreach ($rows as $rowIdx => $data) {
            $rowNum = $rowIdx + 2;
            foreach (array_values($data) as $colIdx => $value) {
                $sheet->setCellValue($this->col($colIdx + 1) . $rowNum, $value);
            }
        }

        // Auto width
        foreach (range(1, count($headers)) as $c) {
            $sheet->getColumnDimension($this->col($c))->setAutoSize(true);
        }

        return $spreadsheet;
    }

    private function xlsxResponse(Spreadsheet $spreadsheet, string $filename): never
    {
        set_time_limit(120);

        $tmp = tempnam(sys_get_temp_dir(), 'rpt_');
        (new Xlsx($spreadsheet))->save($tmp);

        // Flush all output buffers so nothing contaminates the binary stream
        while (ob_get_level()) { ob_end_clean(); }

        // Persist session before we exit()
        request()->session()->save();

        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . filesize($tmp));
        header('Cache-Control: no-store, no-cache, must-revalidate');
        header('Pragma: no-cache');

        readfile($tmp);
        @unlink($tmp);
        exit(0);
    }

    // ─── Notas de Venta ────────────────────────────────────────────────────────

    public function notasDeVenta()
    {
        return view('admin.reportes.notas-de-venta');
    }

    public function notasDeVentaData(Request $request)
    {
        $sortBy  = $request->get('sort_by', 'fecha');
        $sortDir = $request->get('sort_dir', 'desc') === 'asc' ? 'asc' : 'desc';
        $perPage = in_array((int)$request->get('per_page'), [25, 50, 100, 200]) ? (int)$request->get('per_page') : 50;
        $page    = max(1, (int)$request->get('page', 1));

        $sortMap = [
            'folio'    => 'sales_orders.folio',
            'fecha'    => 'sales_orders.fecha',
            'cliente'  => 'clients.nombre',
            'producto' => 'products.nombre',
            'cantidad' => 'sales_order_items.cantidad',
            'precio'   => 'sales_order_items.precio',
            'total'    => 'sales_order_items.total',
        ];
        $sortCol = $sortMap[$sortBy] ?? 'sales_orders.fecha';

        $q = $this->buildNotasDeVentaQuery($request)->orderBy($sortCol, $sortDir);

        $total      = (clone $q)->count();
        $totalMonto = (clone $q)->sum('sales_order_items.total');
        $items      = $q->skip(($page - 1) * $perPage)->take($perPage)->get();

        $rows = $items->map(fn($i) => [
            'folio'      => $i->folio,
            'fecha'      => $i->fecha ? \Carbon\Carbon::parse($i->fecha)->format('d/m/Y') : '—',
            'cliente'    => $i->cliente ?? '—',
            'producto'   => $i->producto,
            'cantidad'   => number_format((float)$i->cantidad, 3),
            'precio'     => number_format((float)$i->precio, 2),
            'total'      => number_format((float)$i->total, 2),
            'observacion'=> $i->descripcion ?? '—',
        ]);

        return response()->json([
            'rows'        => $rows,
            'total'       => $total,
            'total_monto' => number_format((float)$totalMonto, 2),
            'page'        => $page,
            'per_page'    => $perPage,
            'last_page'   => max(1, (int)ceil($total / $perPage)),
        ]);
    }

    public function notasDeVentaExport(Request $request)
    {
        $items = $this->buildNotasDeVentaQuery($request)
            ->orderBy('sales_orders.fecha', 'desc')
            ->get();

        $headers = ['Nota', 'Fecha', 'Cliente', 'Producto', 'Cantidad', 'Precio', 'Total', 'Observaciones'];
        $rows = $items->map(fn($i) => [
            $i->folio,
            $i->fecha ? \Carbon\Carbon::parse($i->fecha)->format('d/m/Y') : '',
            $i->cliente ?? '',
            $i->producto,
            (float)$i->cantidad,
            (float)$i->precio,
            (float)$i->total,
            $i->descripcion ?? '',
        ])->toArray();

        $spreadsheet = $this->makeSpreadsheet($headers, $rows, 'Notas de Venta');

        // Format numeric columns
        $sheet = $spreadsheet->getActiveSheet();
        $lastRow = count($rows) + 1;
        if ($lastRow > 1) {
            $sheet->getStyle("E2:G{$lastRow}")->getNumberFormat()->setFormatCode('#,##0.000');
            $sheet->getStyle("F2:G{$lastRow}")->getNumberFormat()->setFormatCode('#,##0.00');
        }

        $from = $request->get('fecha_desde', now()->startOfMonth()->format('d-m-Y'));
        $to   = $request->get('fecha_hasta', now()->format('d-m-Y'));

        return $this->xlsxResponse($spreadsheet, "notas-de-venta_{$from}_{$to}.xlsx");
    }

    // ─── Ventas por Producto ───────────────────────────────────────────────────

    public function ventasPorProducto()
    {
        return view('admin.reportes.ventas-por-producto');
    }

    public function ventasPorProductoData(Request $request)
    {
        $rows = $this->buildVentasPorProductoQuery($request)
            ->orderByDesc('monto_total')
            ->get();

        $totalMonto = $rows->sum('monto_total');
        $totalKg    = $rows->sum('total_kg');

        $data = $rows->map(fn($r) => [
            'producto'        => $r->producto,
            'total_kg'        => number_format((float)$r->total_kg, 3),
            'monto_total'     => number_format((float)$r->monto_total, 2),
            'precio_promedio' => number_format((float)$r->precio_promedio, 2),
            'total_piezas'    => (int)$r->total_piezas,
        ]);

        return response()->json([
            'rows'        => $data,
            'count'       => $rows->count(),
            'total_monto' => number_format((float)$totalMonto, 2),
            'total_kg'    => number_format((float)$totalKg, 3),
        ]);
    }

    public function ventasPorProductoExport(Request $request)
    {
        $rows = $this->buildVentasPorProductoQuery($request)
            ->orderByDesc('monto_total')
            ->get();

        $headers = ['Producto', 'Total KG', 'Total $', 'Precio Promedio', 'Piezas'];
        $data = $rows->map(fn($r) => [
            $r->producto,
            (float)$r->total_kg,
            (float)$r->monto_total,
            (float)$r->precio_promedio,
            (int)$r->total_piezas,
        ])->toArray();

        // Fila de totales
        $data[] = [
            'TOTAL',
            (float)$rows->sum('total_kg'),
            (float)$rows->sum('monto_total'),
            '',
            '',
        ];

        $spreadsheet = $this->makeSpreadsheet($headers, $data, 'Ventas por Producto');
        $sheet = $spreadsheet->getActiveSheet();
        $lastRow = count($data) + 1;

        if ($lastRow > 2) {
            $sheet->getStyle("B2:D{$lastRow}")->getNumberFormat()->setFormatCode('#,##0.000');
            $sheet->getStyle("C2:D{$lastRow}")->getNumberFormat()->setFormatCode('#,##0.00');
            // Totales row bold
            $sheet->getStyle("A{$lastRow}:E{$lastRow}")->getFont()->setBold(true);
        }

        $from = $request->get('fecha_desde', now()->startOfMonth()->format('d-m-Y'));
        $to   = $request->get('fecha_hasta', now()->format('d-m-Y'));

        return $this->xlsxResponse($spreadsheet, "ventas-por-producto_{$from}_{$to}.xlsx");
    }

    // ─── Liquidaciones ────────────────────────────────────────────────────────

    public function liquidaciones()
    {
        $routes  = ShippingRoute::orderBy('nombre')->get(['id', 'nombre']);
        $drivers = Driver::orderBy('nombre')->get(['id', 'nombre']);
        return view('admin.reportes.liquidaciones', compact('routes', 'drivers'));
    }

    public function liquidacionesData(Request $request)
    {
        $perPage = in_array((int)$request->get('per_page'), [25, 50, 100]) ? (int)$request->get('per_page') : 50;
        $page    = max(1, (int)$request->get('page', 1));

        $q = $this->buildLiquidacionesQuery($request);

        $total      = (clone $q)->count();
        $totalMonto = (clone $q)->sum('sales_orders.total');
        $items      = $q->skip(($page - 1) * $perPage)->take($perPage)->get();

        $settlementColors = [
            'PENDIENTE' => 'bg-amber-100 text-amber-700',
            'LIQUIDADO' => 'bg-emerald-100 text-emerald-700',
        ];
        $orderLabels  = $this->orderStatusLabels();
        $orderClasses = $this->orderStatusClasses();

        $rows = $items->map(fn($s) => [
            'folio'          => $s->folio,
            'cliente'        => $s->cliente_nombre ?? '—',
            'ruta'           => $s->ruta_nombre    ?? '—',
            'chofer'         => $s->chofer_nombre   ?? '—',
            'fecha'          => $s->fecha ? \Carbon\Carbon::parse($s->fecha)->format('d/m/Y') : '—',
            'total'          => number_format((float)$s->total, 2),
            'estatus'        => $s->driver_settlement_status ?? 'PENDIENTE',
            'est_class'      => $settlementColors[$s->driver_settlement_status ?? 'PENDIENTE'] ?? 'bg-gray-100 text-gray-700',
            'estatus_pedido' => $orderLabels[$s->order_status] ?? $s->order_status,
            'pedido_class'   => $orderClasses[$s->order_status] ?? 'bg-gray-100 text-gray-700',
        ]);

        return response()->json([
            'rows'        => $rows,
            'total'       => $total,
            'total_monto' => number_format((float)$totalMonto, 2),
            'page'        => $page,
            'per_page'    => $perPage,
            'last_page'   => max(1, (int)ceil($total / $perPage)),
        ]);
    }

    public function liquidacionesConcentrado(Request $request)
    {
        $items = $this->buildLiquidacionesQuery($request)->get();

        $orderLabels  = $this->orderStatusLabels();
        $orderClasses = $this->orderStatusClasses();

        // Group by ruta
        $grouped = $items->groupBy('ruta_nombre')->map(function ($rows, $ruta) use ($orderLabels, $orderClasses) {
            return [
                'ruta'    => $ruta ?? 'Sin ruta',
                'notas'   => $rows->map(fn($s) => [
                    'folio'          => $s->folio,
                    'cliente'        => $s->cliente_nombre ?? '—',
                    'fecha'          => $s->fecha ? \Carbon\Carbon::parse($s->fecha)->format('d/m/Y') : '—',
                    'total'          => (float)$s->total,
                    'total_fmt'      => number_format((float)$s->total, 2),
                    'estatus'        => $s->driver_settlement_status ?? 'PENDIENTE',
                    'estatus_pedido' => $orderLabels[$s->order_status] ?? $s->order_status,
                    'pedido_class'   => $orderClasses[$s->order_status] ?? 'bg-gray-100 text-gray-700',
                ])->values(),
                'subtotal'     => (float)$rows->sum('total'),
                'subtotal_fmt' => number_format((float)$rows->sum('total'), 2),
                'count'        => $rows->count(),
            ];
        })->values();

        return response()->json([
            'rutas'               => $grouped,
            'total'               => $items->count(),
            'total_monto'         => number_format((float)$items->sum('total'), 2),
            'pendientes_procesar' => $this->pendientesPorDespachar(),
        ]);
    }

    public function liquidacionesExport(Request $request)
    {
        $items = $this->buildLiquidacionesQuery($request)->get();
        $fecha = $request->get('fecha', now()->format('d/m/Y'));

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Liquidaciones');

        // Title row
        $sheet->setCellValue('A1', "LISTA DE LIQUIDACIONES - {$fecha}");
        $sheet->mergeCells('A1:F1');
        $sheet->getStyle('A1')->applyFromArray([
            'font'      => ['bold' => true, 'size' => 13, 'color' => ['rgb' => '1E3A5F']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);

        $row = 2;
        $totalGeneral = 0.0;
        $orderLabels = $this->orderStatusLabels();

        $grouped = $items->groupBy('ruta_nombre');

        foreach ($grouped as $rutaNombre => $notas) {
            // Route header
            $ruta = $rutaNombre ?? 'Sin ruta';
            $sheet->setCellValue("A{$row}", strtoupper($ruta));
            $sheet->mergeCells("A{$row}:F{$row}");
            $sheet->getStyle("A{$row}")->applyFromArray([
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '4F46E5']],
            ]);
            $row++;

            // Column headers
            foreach (['Nota','Cliente','Fecha','Monto','Estatus pedido','Liquidación'] as $ci => $h) {
                $cell = $this->col($ci + 1) . $row;
                $sheet->setCellValue($cell, $h);
                $sheet->getStyle($cell)->applyFromArray([
                    'font' => ['bold' => true, 'color' => ['rgb' => '374151']],
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'E5E7EB']],
                ]);
            }
            $row++;

            $subtotal = 0.0;
            foreach ($notas as $s) {
                $monto = (float)$s->total;
                $subtotal += $monto;
                $sheet->setCellValue("A{$row}", $s->folio);
                $sheet->setCellValue("B{$row}", $s->cliente_nombre ?? '');
                $sheet->setCellValue("C{$row}", $s->fecha ? \Carbon\Carbon::parse($s->fecha)->format('d/m/Y') : '');
                $sheet->setCellValue("D{$row}", $monto);
                $sheet->setCellValue("E{$row}", $orderLabels[$s->order_status] ?? $s->order_status);
                $sheet->setCellValue("F{$row}", $s->driver_settlement_status ?? 'PENDIENTE');
                $sheet->getStyle("D{$row}")->getNumberFormat()->setFormatCode('#,##0.00');
                $row++;
            }

            // Subtotal row
            $sheet->setCellValue("C{$row}", "Subtotal {$ruta}:");
            $sheet->setCellValue("D{$row}", $subtotal);
            $sheet->getStyle("C{$row}:D{$row}")->getFont()->setBold(true);
            $sheet->getStyle("D{$row}")->getNumberFormat()->setFormatCode('#,##0.00');
            $row += 2; // blank line between routes

            $totalGeneral += $subtotal;
        }

        // Grand total
        $sheet->setCellValue("C{$row}", 'TOTAL GENERAL:');
        $sheet->setCellValue("D{$row}", $totalGeneral);
        $sheet->getStyle("C{$row}:D{$row}")->applyFromArray([
            'font' => ['bold' => true, 'size' => 12],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1E3A5F']],
        ]);
        $sheet->getStyle("C{$row}:D{$row}")->getFont()->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color('FFFFFFFF'));
        $sheet->getStyle("D{$row}")->getNumberFormat()->setFormatCode('#,##0.00');
        $row += 3; // blank space before the pending section

        // Pedidos procesados pendientes por despachar
        $pendientes = $this->pendientesPorDespachar();

        $sheet->setCellValue("A{$row}", 'PEDIDOS PROCESADOS PENDIENTES POR DESPACHAR');
        $sheet->mergeCells("A{$row}:F{$row}");
        $sheet->getStyle("A{$row}")->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'D97706']],
        ]);
        $row++;

        if ($pendientes->isEmpty()) {
            $sheet->setCellValue("A{$row}", 'No hay pedidos procesados pendientes por despachar.');
            $sheet->mergeCells("A{$row}:F{$row}");
            $row++;
        } else {
            foreach (['Nota','Cliente','Ruta','Programado','Monto'] as $ci => $h) {
                $cell = $this->col($ci + 1) . $row;
                $sheet->setCellValue($cell, $h);
                $sheet->getStyle($cell)->applyFromArray([
                    'font' => ['bold' => true, 'color' => ['rgb' => '374151']],
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'FDE68A']],
                ]);
            }
            $row++;

            foreach ($pendientes as $p) {
                $sheet->setCellValue("A{$row}", $p['folio']);
                $sheet->setCellValue("B{$row}", $p['cliente']);
                $sheet->setCellValue("C{$row}", $p['ruta']);
                $sheet->setCellValue("D{$row}", $p['fecha']);
                $sheet->setCellValue("E{$row}", (float) str_replace(',', '', $p['total']));
                $sheet->getStyle("E{$row}")->getNumberFormat()->setFormatCode('#,##0.00');
                $row++;
            }
        }

        foreach (range(1, 6) as $c) {
            $sheet->getColumnDimension($this->col($c))->setAutoSize(true);
        }

        $fechaFile = str_replace('/', '-', $fecha);
        return $this->xlsxResponse($spreadsheet, "liquidaciones_{$fechaFile}.xlsx");
    }
}
