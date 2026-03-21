<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Sale;
use App\Models\SalesOrder;
use App\Models\ArMovement;
use App\Models\Product;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function index()
    {
        $today     = Carbon::today();
        $yesterday = Carbon::yesterday();

        // ── Ventas del día (sales_orders ENTREGADOS + sales POS) ──────────────
        $soHoy   = (float) SalesOrder::whereDate('updated_at', $today)
                        ->where('status', 'ENTREGADO')->sum('total');
        $posHoy  = (float) Sale::whereDate('fecha', $today)->sum('total');
        $ventasHoy = $soHoy + $posHoy;

        $soAyer  = (float) SalesOrder::whereDate('updated_at', $yesterday)
                        ->where('status', 'ENTREGADO')->sum('total');
        $posAyer = (float) Sale::whereDate('fecha', $yesterday)->sum('total');
        $ventasAyer = $soAyer + $posAyer;

        $variacion = $ventasAyer > 0
            ? round((($ventasHoy - $ventasAyer) / $ventasAyer) * 100, 1)
            : ($ventasHoy > 0 ? 100 : 0);

        // ── Pedidos del día ───────────────────────────────────────────────────
        $pedidosHoy  = SalesOrder::whereDate('created_at', $today)->count();
        $pedidosAyer = SalesOrder::whereDate('created_at', $yesterday)->count();

        // ── CxC saldo total ───────────────────────────────────────────────────
        $cxcTotal = (float) ArMovement::selectRaw(
            "COALESCE(SUM(CASE WHEN tipo='CARGO' THEN monto ELSE -monto END), 0) as saldo"
        )->value('saldo');

        // ── Pedidos por estado ────────────────────────────────────────────────
        $pedidosPorEstado = SalesOrder::selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status')
            ->toArray();

        $estados = [
            'BORRADOR'     => ['label' => 'Borrador',     'color' => 'bg-gray-200 text-gray-700'],
            'APROBADO'     => ['label' => 'Aprobado',     'color' => 'bg-blue-100 text-blue-700'],
            'PREPARANDO'   => ['label' => 'Preparando',   'color' => 'bg-sky-100 text-sky-700'],
            'PROCESADO'    => ['label' => 'Procesado',    'color' => 'bg-amber-100 text-amber-700'],
            'EN_RUTA'      => ['label' => 'En ruta',      'color' => 'bg-violet-100 text-violet-700'],
            'ENTREGADO'    => ['label' => 'Entregado',    'color' => 'bg-emerald-100 text-emerald-700'],
            'NO_ENTREGADO' => ['label' => 'No entregado', 'color' => 'bg-orange-100 text-orange-700'],
            'CANCELADO'    => ['label' => 'Cancelado',    'color' => 'bg-rose-100 text-rose-700'],
        ];

        // ── Gráfica últimos 7 días ────────────────────────────────────────────
        $dias = collect(range(6, 0))->map(fn($i) => Carbon::today()->subDays($i));

        $soSeries = SalesOrder::selectRaw('DATE(updated_at) as dia, SUM(total) as total')
            ->where('status', 'ENTREGADO')
            ->whereBetween('updated_at', [$dias->first()->startOfDay(), now()])
            ->groupBy('dia')
            ->pluck('total', 'dia');

        $posSeries = Sale::selectRaw('DATE(fecha) as dia, SUM(total) as total')
            ->whereBetween('fecha', [$dias->first()->toDateString(), $dias->last()->toDateString()])
            ->groupBy('dia')
            ->pluck('total', 'dia');

        $chartLabels = $dias->map(fn($d) => $d->format('d/m'))->values()->toArray();
        $chartSO     = $dias->map(fn($d) => round((float)($soSeries[$d->toDateString()]  ?? 0), 2))->values()->toArray();
        $chartPOS    = $dias->map(fn($d) => round((float)($posSeries[$d->toDateString()] ?? 0), 2))->values()->toArray();

        // ── Últimos pedidos ───────────────────────────────────────────────────
        $ultimosPedidos = SalesOrder::with('client:id,nombre')
            ->orderByDesc('created_at')
            ->limit(6)
            ->get(['id','folio','client_id','status','total','created_at']);

        return view('admin.dashboard', compact(
            'ventasHoy','ventasAyer','variacion',
            'pedidosHoy','pedidosAyer',
            'cxcTotal',
            'pedidosPorEstado','estados',
            'chartLabels','chartSO','chartPOS',
            'ultimosPedidos',
        ));
    }
}