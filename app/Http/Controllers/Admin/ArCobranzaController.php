<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\SalesOrder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class ArCobranzaController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('can:ver cxc'),
        ];
    }

    private function buildQuery(Request $request)
    {
        $clienteDesde  = $request->get('cliente_desde');
        $clienteHasta  = $request->get('cliente_hasta');
        $fechaVencDesde = $request->get('fecha_venc_desde');
        $fechaVencHasta = $request->get('fecha_venc_hasta');
        $soloConSaldo   = $request->boolean('solo_con_saldo', true);
        $status         = $request->get('status', 'todos'); // todos | vencidas | vigentes

        return SalesOrder::query()
            ->select([
                'sales_orders.id',
                'sales_orders.folio',
                'sales_orders.fecha',
                'sales_orders.total',
                'sales_orders.saldo_pendiente',
                'sales_orders.status',
                'sales_orders.cobrado_at',
                'clients.id as client_id',
                'clients.nombre as client_nombre',
                'clients.credito_dias',
                DB::raw('DATE_ADD(sales_orders.fecha, INTERVAL COALESCE(clients.credito_dias, 30) DAY) AS fecha_vencimiento'),
                DB::raw('(sales_orders.total - COALESCE(sales_orders.saldo_pendiente, sales_orders.total)) AS abonos'),
            ])
            ->join('clients', 'clients.id', '=', 'sales_orders.client_id')
            ->where('sales_orders.payment_method', 'CREDITO')
            ->whereIn('sales_orders.status', ['ENTREGADO', 'DESPACHADO', 'PROCESADO'])
            ->when($soloConSaldo, fn($q) =>
                $q->where(function ($q) {
                    $q->whereNull('sales_orders.saldo_pendiente')
                      ->orWhere('sales_orders.saldo_pendiente', '>', 0);
                })
            )
            ->when($clienteDesde && !$clienteHasta, fn($q) =>
                $q->where('clients.nombre', $clienteDesde)
            )
            ->when($clienteDesde && $clienteHasta, fn($q) =>
                $q->where('clients.nombre', '>=', $clienteDesde)
                  ->where('clients.nombre', '<=', $clienteHasta . 'zzz')
            )
            ->when($fechaVencDesde, fn($q) =>
                $q->havingRaw('fecha_vencimiento >= ?', [$fechaVencDesde])
            )
            ->when($fechaVencHasta, fn($q) =>
                $q->havingRaw('fecha_vencimiento <= ?', [$fechaVencHasta])
            )
            ->when($status === 'vencidas', fn($q) =>
                $q->havingRaw('fecha_vencimiento < CURDATE()')
            )
            ->when($status === 'vigentes', fn($q) =>
                $q->havingRaw('fecha_vencimiento >= CURDATE()')
            )
            ->orderBy('clients.nombre')
            ->orderBy('sales_orders.fecha');
    }

    public function index(Request $request)
    {
        $rows = $this->buildQuery($request)->get();

        $porCliente = $rows->groupBy('client_id');

        $totales = [
            'cargos' => $rows->sum('total'),
            'abonos' => $rows->sum('abonos'),
            'saldo'  => $rows->sum(fn($r) => $r->saldo_pendiente ?? $r->total),
        ];

        $clientes = Client::where('activo', 1)->orderBy('nombre')->get(['id', 'nombre']);

        return view('admin.ar.cobranza-general', compact('porCliente', 'totales', 'clientes'));
    }

    public function exportExcel(Request $request)
    {
        $rows = $this->buildQuery($request)->get();
        $porCliente = $rows->groupBy('client_id');

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Cobranza General');

        // Empresa
        $empresa = app(\App\Services\CompanyService::class)->activa();
        $nombreEmpresa = $empresa?->fiscalData?->razon_social ?? $empresa?->nombre_comercial ?? config('app.name');

        // Título
        $sheet->mergeCells('A1:I1');
        $sheet->setCellValue('A1', $nombreEmpresa);
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
        $sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        $sheet->mergeCells('A2:I2');
        $sheet->setCellValue('A2', 'Cobranza General');
        $sheet->getStyle('A2')->getFont()->setBold(true)->setSize(12);
        $sheet->getStyle('A2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        // Filtros info
        $fechaVencDesde = $request->get('fecha_venc_desde', '');
        $fechaVencHasta = $request->get('fecha_venc_hasta', '');
        $infoFiltro = 'Fecha de vencimiento: ' .
            ($fechaVencDesde ? 'desde ' . $fechaVencDesde : '') .
            ($fechaVencHasta ? ' hasta ' . $fechaVencHasta : '') ?: 'Todos';
        $sheet->mergeCells('A3:I3');
        $sheet->setCellValue('A3', $infoFiltro);
        $sheet->getStyle('A3')->getFont()->setSize(9)->setItalic(true);

        // Encabezados
        $row = 5;
        $headers = ['Cliente', 'Concepto', 'Documento', 'Num.', 'Fecha Aplic.', 'Fecha Venc.', 'Cargos', 'Abonos', 'Saldo'];
        $cols    = ['A','B','C','D','E','F','G','H','I'];
        foreach ($headers as $i => $h) {
            $sheet->setCellValue($cols[$i] . $row, $h);
        }
        $sheet->getStyle("A{$row}:I{$row}")->getFont()->setBold(true);
        $sheet->getStyle("A{$row}:I{$row}")->getFill()
            ->setFillType(Fill::FILL_SOLID)
            ->getStartColor()->setARGB('FFD1D5DB');

        $row++;

        // Datos
        foreach ($porCliente as $clientId => $notas) {
            $numRow = 1;
            $subtotalCargos = 0;
            $subtotalAbonos = 0;
            $subtotalSaldo  = 0;

            foreach ($notas as $nota) {
                $saldo  = $nota->saldo_pendiente ?? $nota->total;
                $cargos = (float) $nota->total;
                $abonos = (float) ($nota->total - $saldo);

                $sheet->setCellValue('A' . $row, $nota->client_nombre);
                $sheet->setCellValue('B' . $row, 'Nota de venta');
                $sheet->setCellValue('C' . $row, $nota->folio);
                $sheet->setCellValue('D' . $row, $numRow);
                $sheet->setCellValue('E' . $row, \Carbon\Carbon::parse($nota->fecha)->format('d/m/Y'));
                $sheet->setCellValue('F' . $row, \Carbon\Carbon::parse($nota->fecha_vencimiento)->format('d/m/Y'));
                $sheet->setCellValue('G' . $row, $cargos);
                $sheet->setCellValue('H' . $row, $abonos);
                $sheet->setCellValue('I' . $row, $saldo);

                $sheet->getStyle("G{$row}:I{$row}")->getNumberFormat()->setFormatCode('#,##0.00');

                $subtotalCargos += $cargos;
                $subtotalAbonos += $abonos;
                $subtotalSaldo  += $saldo;
                $numRow++;
                $row++;
            }

            // Subtotal cliente
            $sheet->setCellValue('F' . $row, 'Subtotal:');
            $sheet->setCellValue('G' . $row, $subtotalCargos);
            $sheet->setCellValue('H' . $row, $subtotalAbonos);
            $sheet->setCellValue('I' . $row, $subtotalSaldo);
            $sheet->getStyle("A{$row}:I{$row}")->getFont()->setBold(true);
            $sheet->getStyle("G{$row}:I{$row}")->getNumberFormat()->setFormatCode('#,##0.00');
            $row += 2;
        }

        // Total general
        $totalCargos = $rows->sum('total');
        $totalSaldo  = $rows->sum(fn($r) => $r->saldo_pendiente ?? $r->total);
        $totalAbonos = $totalCargos - $totalSaldo;

        $sheet->setCellValue('E' . $row, 'TOTAL GENERAL:');
        $sheet->setCellValue('G' . $row, $totalCargos);
        $sheet->setCellValue('H' . $row, $totalAbonos);
        $sheet->setCellValue('I' . $row, $totalSaldo);
        $sheet->getStyle("A{$row}:I{$row}")->getFont()->setBold(true)->setSize(11);
        $sheet->getStyle("G{$row}:I{$row}")->getNumberFormat()->setFormatCode('#,##0.00');
        $sheet->getStyle("A{$row}:I{$row}")->getFill()
            ->setFillType(Fill::FILL_SOLID)
            ->getStartColor()->setARGB('FFFEF3C7');

        // Anchos
        foreach (['A'=>28,'B'=>15,'C'=>14,'D'=>6,'E'=>13,'F'=>13,'G'=>14,'H'=>14,'I'=>14] as $col => $w) {
            $sheet->getColumnDimension($col)->setWidth($w);
        }

        $writer = new Xlsx($spreadsheet);
        $filename = 'cobranza-general-' . now()->format('Ymd-His') . '.xlsx';

        return response()->streamDownload(function () use ($writer) {
            $writer->save('php://output');
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    public function exportPdf(Request $request)
    {
        $rows = $this->buildQuery($request)->get();
        $porCliente = $rows->groupBy('client_id');

        $totales = [
            'cargos' => $rows->sum('total'),
            'abonos' => $rows->sum('abonos'),
            'saldo'  => $rows->sum(fn($r) => $r->saldo_pendiente ?? $r->total),
        ];

        $empresa = app(\App\Services\CompanyService::class)->activa();
        $filtros  = $request->only(['fecha_venc_desde', 'fecha_venc_hasta', 'status', 'solo_con_saldo', 'cliente_desde', 'cliente_hasta']);

        $html = view('admin.ar.cobranza-pdf', compact('porCliente', 'totales', 'empresa', 'filtros'))->render();

        $pdf = app('dompdf.wrapper');
        $pdf->loadHTML($html)->setPaper('letter', 'landscape');

        return $pdf->download('cobranza-general-' . now()->format('Ymd') . '.pdf');
    }
}
