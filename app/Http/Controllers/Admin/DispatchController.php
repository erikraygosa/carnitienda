<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Dispatch;
use App\Models\DispatchItem;
use App\Models\AccountsReceivable;
use App\Models\SalesOrder;
use App\Models\Warehouse;
use App\Models\ShippingRoute;
use App\Models\Driver;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Carbon\Carbon;

class DispatchController extends Controller
{
    public function index() {
        return view('admin.dispatches.index'); // Livewire table
    }

    public function create(Request $req) {
        $warehouses = Warehouse::orderBy('nombre')->get(['id','nombre']);
        $routes     = ShippingRoute::orderBy('nombre')->get(['id','nombre']);
        $drivers    = Driver::orderBy('nombre')->get(['id','nombre']);
        $accounts   = AccountsReceivable::where('saldo','>',0)
            ->orderByDesc('fecha')
            ->limit(100)
            ->get(['id','client_id','folio_documento','saldo','fecha']);

        // Pedidos candidatos (APROBADO/PROCESADO/ PREPARANDO): aún sin despacho o no cerrados
        $orders = SalesOrder::query()
            ->whereIn('status', ['APROBADO','PREPARANDO','PROCESADO'])
            ->latest()->limit(100)
            ->get(['id','folio','client_id','status','programado_para']);

        return view('admin.dispatches.create', compact('warehouses','routes','drivers','orders','accounts'));
    }

    public function store(Request $request) {
        $data = $request->validate([
            'warehouse_id'      => ['nullable','exists:warehouses,id'],
            'shipping_route_id' => ['nullable','exists:shipping_routes,id'],
            'driver_id'         => ['nullable','exists:drivers,id'],
            'vehicle'           => ['nullable','string','max:50'],
            'fecha'             => ['required','date'],
            'notas'             => ['nullable','string'],
            'orders'            => ['required','array','min:1'],
            'orders.*'          => ['integer','exists:sales_orders,id'],
            'accounts_receivable'   => ['nullable','array'],
            'accounts_receivable.*' => ['integer','exists:accounts_receivable,id'],
        ]);

        return DB::transaction(function () use ($data) {
            $dispatch = Dispatch::create([
                'warehouse_id'      => $data['warehouse_id'] ?? null,
                'shipping_route_id' => $data['shipping_route_id'] ?? null,
                'driver_id'         => $data['driver_id'] ?? null,
                'vehicle'           => $data['vehicle'] ?? null,
                'fecha'             => Carbon::parse($data['fecha']),
                'status'            => 'PLANEADO',
                'notas'             => $data['notas'] ?? null,
            ]);

            $orders = SalesOrder::whereIn('id', $data['orders'])->get(['id','folio']);
            foreach ($orders as $o) {
                DispatchItem::create([
                    'dispatch_id'    => $dispatch->id,
                    'sales_order_id' => $o->id,
                    'referencia'     => $o->folio,
                    'status'         => 'ASIGNADO',
                ]);
            }

            if (!empty($data['accounts_receivable'])) {
                AccountsReceivable::whereIn('id', $data['accounts_receivable'])
                    ->update(['driver_id' => $data['driver_id'] ?? null]);
            }

            session()->flash('swal', [
                'icon'=>'success','title'=>'¡Listo!','text'=>'Despacho creado.'
            ]);

            return redirect()->route('admin.dispatches.edit', $dispatch);
        });
    }

    public function edit(Dispatch $dispatch) {
        $warehouses = Warehouse::orderBy('nombre')->get(['id','nombre']);
        $routes     = ShippingRoute::orderBy('nombre')->get(['id','nombre']);
        $drivers    = Driver::orderBy('nombre')->get(['id','nombre']);

        $statusClasses = [
            'PLANEADO'   => 'bg-gray-100 text-gray-700',
            'PREPARANDO' => 'bg-sky-100 text-sky-700',
            'CARGADO'    => 'bg-amber-100 text-amber-700',
            'EN_RUTA'    => 'bg-violet-100 text-violet-700',
            'ENTREGADO'  => 'bg-emerald-100 text-emerald-700',
            'CERRADO'    => 'bg-blue-100 text-blue-700',
            'CANCELADO'  => 'bg-rose-100 text-rose-700',
        ];

        return view('admin.dispatches.edit', compact('dispatch','warehouses','routes','drivers','statusClasses'));
    }

    public function update(Request $request, Dispatch $dispatch) {
        $data = $request->validate([
            'warehouse_id'      => ['nullable','exists:warehouses,id'],
            'shipping_route_id' => ['nullable','exists:shipping_routes,id'],
            'driver_id'         => ['nullable','exists:drivers,id'],
            'vehicle'           => ['nullable','string','max:50'],
            'fecha'             => ['required','date'],
            'notas'             => ['nullable','string'],
            'status'            => ['required', Rule::in(['PLANEADO','PREPARANDO','CARGADO','EN_RUTA','ENTREGADO','CERRADO','CANCELADO'])],
        ]);

        $dispatch->update($data);

        session()->flash('swal', ['icon'=>'success','title'=>'Actualizado','text'=>'Despacho actualizado.']);
        return back();
    }

    public function destroy(Dispatch $dispatch) {
        $dispatch->delete();
        session()->flash('swal',['icon'=>'success','title'=>'Eliminado','text'=>'Despacho eliminado.']);
        return redirect()->route('admin.dispatches.index');
    }

    // ===== Acciones de flujo =====
    public function preparar(Dispatch $dispatch){ $dispatch->update(['status'=>'PREPARANDO']); return back(); }
    public function cargar(Dispatch $dispatch)  { $dispatch->update(['status'=>'CARGADO']);    return back(); }
    public function enRuta(Dispatch $dispatch)  { $dispatch->update(['status'=>'EN_RUTA']);    return back(); }
    public function entregar(Dispatch $dispatch){ $dispatch->update(['status'=>'ENTREGADO']);  return back(); }
    public function cerrar(Dispatch $dispatch)  { $dispatch->update(['status'=>'CERRADO']);    return back(); }
    public function cancelar(Dispatch $dispatch){ $dispatch->update(['status'=>'CANCELADO']);  return back(); }
}
