<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Sale;
use App\Models\SalesOrder;
use App\Models\PaymentType;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function index()
    {
        $today = Carbon::today();

        // KPIs ventas del día
        $ventasCount = Sale::whereDate('fecha', $today)->count();
        $ventasTotal = (float) Sale::whereDate('fecha', $today)->sum('total');

        // === EFECTIVO ===
        // 1) Intentamos encontrar el ID del tipo de pago "EFECTIVO"
        //    (ajusta a tu dato real: clave o descripcion)
        $efectivoTypeId = PaymentType::where('clave', 'EFECTIVO')
                            ->value('id')
                         ?? PaymentType::where('descripcion', 'like', 'EFECTIVO%')->value('id');

        // 2) Si existe el tipo de pago, calculamos por payment_type_id
        if ($efectivoTypeId) {
            $ventasEfectivo = (float) Sale::whereDate('fecha', $today)
                ->where('payment_type_id', $efectivoTypeId)
                ->sum('total');
        } else {
            // 3) Fallback: si no hay tipo "EFECTIVO" configurado,
            //    usamos lo efectivamente cobrado en efectivo (si lo manejas)
            $ventasEfectivo = (float) Sale::whereDate('fecha', $today)
                ->sum(DB::raw('COALESCE(cobrado_efectivo,0)'));
        }

        // Pedidos en tránsito (ajusta a tus estados reales)
        $statusesTransito = ['DESPACHADO', 'EN RUTA'];
        $pedidosTransito  = SalesOrder::whereIn('status', $statusesTransito)->count();

        // Listas
        $ultimasVentas = Sale::orderByDesc('fecha')
            ->limit(5)->get(['id','fecha','total','payment_type_id']);
        $ultimosPedidosTransito = SalesOrder::whereIn('status',$statusesTransito)
            ->orderByDesc('updated_at')->limit(5)->get(['id','status','updated_at']);

        return view('admin.dashboard', compact(
            'ventasCount','ventasTotal','ventasEfectivo',
            'pedidosTransito','ultimasVentas','ultimosPedidosTransito'
        ));
    }
}
