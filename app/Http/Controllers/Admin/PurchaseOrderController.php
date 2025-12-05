<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Provider;
use App\Models\Warehouse;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PurchaseOrderController extends Controller
{
    public function index()
    {
        return view('admin.purchase_orders.index');
    }

    public function create()
    {
        return view('admin.purchase_orders.create', [
            'providers'  => Provider::orderBy('nombre')->get(),
            'warehouses' => Warehouse::orderBy('nombre')->get(),
            'products'   => Product::orderBy('nombre')->get(['id','nombre']),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'provider_id'  => ['required','exists:providers,id'],
            'warehouse_id' => ['required','exists:warehouses,id'],
            'fecha'        => ['required','date'],
            'expected_at'  => ['nullable','date'],
            'currency'     => ['required','string','max:10'],
            'observaciones'=> ['nullable','string'],
            'folio'        => ['nullable','string','max:50','unique:purchase_orders,folio'],
            'payment_method'  => ['required','in:CREDITO,TRANSFERENCIA,CONTRAENTREGA,EFECTIVO'],
            'items'        => ['required','array','min:1'],
            'items.*.product_id' => ['required','exists:products,id'],
            'items.*.qty_ordered'=> ['required','numeric','min:0.001'],
            'items.*.price'      => ['required','numeric','min:0'],
            'items.*.discount'   => ['nullable','numeric','min:0'],
            'items.*.tax_rate'   => ['nullable','numeric','min:0'],
        ]);

        // Folio: usa el dado o genera uno
        $folio = str($request->input('folio', ''))->trim();
        if ($folio->isEmpty()) {
            $seq = (PurchaseOrder::max('id') ?? 0) + 1;
            $folio = 'OC-'.now()->format('Ymd').'-'.str_pad($seq, 4, '0', STR_PAD_LEFT);
        }

        $order = null; // inicializa para pasarlo por referencia

        DB::transaction(function () use ($data, $folio, &$order) {
            // Calcular totales
            $subtotal = 0; $discount_total = 0; $tax_total = 0; $grand = 0;

            foreach ($data['items'] as $it) {
                $line_sub  = $it['qty_ordered'] * $it['price'];
                $line_disc = $it['discount'] ?? 0;
                $base      = max($line_sub - $line_disc, 0);
                $line_tax  = ($it['tax_rate'] ?? 0) * 0.01 * $base;
                $line_total= $base + $line_tax;

                $subtotal       += $line_sub;
                $discount_total += $line_disc;
                $tax_total      += $line_tax;
                $grand          += $line_total;
            }

            $order = PurchaseOrder::create([
                'provider_id'    => $data['provider_id'],
                'warehouse_id'   => $data['warehouse_id'],
                'user_id'        => auth()->id(),
                'folio'          => (string)$folio,
                'fecha'          => $data['fecha'],
                'expected_at'    => $data['expected_at'] ?? null,
                'status'         => 'draft',
                'subtotal'       => $subtotal,
                'payment_method' => $data['payment_method'],
                'discount_total' => $discount_total,
                'tax_total'      => $tax_total,
                'total'          => $grand,
                'currency'       => $data['currency'],
                'observaciones'  => $data['observaciones'] ?? null,
            ]);

            foreach ($data['items'] as $it) {
                $line_sub  = $it['qty_ordered'] * $it['price'];
                $line_disc = $it['discount'] ?? 0;
                $base      = max($line_sub - $line_disc, 0);
                $line_tax  = ($it['tax_rate'] ?? 0) * 0.01 * $base;
                $line_total= $base + $line_tax;

                PurchaseOrderItem::create([
                    'purchase_order_id' => $order->id,
                    'product_id'        => $it['product_id'],
                    'qty_ordered'       => $it['qty_ordered'],
                    'price'             => $it['price'],
                    'discount'          => $line_disc,
                    'tax_rate'          => $it['tax_rate'] ?? 0,
                    'total'             => $line_total,
                ]);
            }
        });

        session()->flash('swal', ['icon'=>'success','title'=>'¡Bien Hecho!','text'=>'Orden de compra creada.']);
        return redirect()->route('admin.purchase-orders.edit', $order);
    }

    public function edit(PurchaseOrder $purchase_order)
    {
        return view('admin.purchase_orders.edit', [
            'order'      => $purchase_order->load('items.product','provider','warehouse'),
            'providers'  => Provider::orderBy('nombre')->get(),
            'warehouses' => Warehouse::orderBy('nombre')->get(),
            'products'   => Product::orderBy('nombre')->get(['id','nombre']),
        ]);
    }

    public function update(Request $request, PurchaseOrder $purchase_order)
    {
        if ($purchase_order->status !== 'draft') {
            return back()->with('swal', ['icon'=>'error','title'=>'Error','text'=>'Solo órdenes en borrador pueden editarse.']);
        }

        $data = $request->validate([
            'provider_id'  => ['required','exists:providers,id'],
            'warehouse_id' => ['required','exists:warehouses,id'],
            'fecha'        => ['required','date'],
            'expected_at'  => ['nullable','date'],
            'currency'     => ['required','string','max:10'],
            'observaciones'=> ['nullable','string'],
            'items'        => ['required','array','min:1'],
            'items.*.product_id' => ['required','exists:products,id'],
            'payment_method'  => ['required','in:CREDITO,TRANSFERENCIA,CONTRAENTREGA,EFECTIVO'],
            'items.*.qty_ordered'=> ['required','numeric','min:0.001'],
            'items.*.price'      => ['required','numeric','min:0'],
            'items.*.discount'   => ['nullable','numeric','min:0'],
            'items.*.tax_rate'   => ['nullable','numeric','min:0'],
        ]);

        DB::transaction(function () use ($purchase_order, $data) {
            $subtotal = 0; $discount_total = 0; $tax_total = 0; $grand = 0;

            foreach ($data['items'] as $it) {
                $line_sub  = $it['qty_ordered'] * $it['price'];
                $line_disc = $it['discount'] ?? 0;
                $base      = max($line_sub - $line_disc, 0);
                $line_tax  = ($it['tax_rate'] ?? 0) * 0.01 * $base;
                $line_total= $base + $line_tax;

                $subtotal       += $line_sub;
                $discount_total += $line_disc;
                $tax_total      += $line_tax;
                $grand          += $line_total;
            }

            $purchase_order->update([
                'provider_id'    => $data['provider_id'],
                'warehouse_id'   => $data['warehouse_id'],
                'fecha'          => $data['fecha'],
                'expected_at'    => $data['expected_at'] ?? null,
                'subtotal'       => $subtotal,
                'discount_total' => $discount_total,
                'tax_total'      => $tax_total,
                'total'          => $grand,
                'currency'       => $data['currency'],
                'observaciones'  => $data['observaciones'] ?? null,
            ]);

            // Reemplazar items
            $purchase_order->items()->delete();

            foreach ($data['items'] as $it) {
                $line_sub  = $it['qty_ordered'] * $it['price'];
                $line_disc = $it['discount'] ?? 0;
                $base      = max($line_sub - $line_disc, 0);
                $line_tax  = ($it['tax_rate'] ?? 0) * 0.01 * $base;
                $line_total= $base + $line_tax;

                PurchaseOrderItem::create([
                    'purchase_order_id' => $purchase_order->id,
                    'product_id'        => $it['product_id'],
                    'qty_ordered'       => $it['qty_ordered'],
                    'price'             => $it['price'],
                    'discount'          => $line_disc,
                    'tax_rate'          => $it['tax_rate'] ?? 0,
                    'total'             => $line_total,
                ]);
            }
        });

        session()->flash('swal', ['icon'=>'success','title'=>'Actualizado','text'=>'Orden de compra actualizada.']);
        return redirect()->route('admin.purchase-orders.edit', $purchase_order);
    }

    public function destroy(PurchaseOrder $purchase_order)
    {
        if ($purchase_order->status !== 'draft') {
            return back()->with('swal', ['icon'=>'error','title'=>'Error','text'=>'Solo órdenes en borrador pueden eliminarse.']);
        }

        $purchase_order->delete();
        session()->flash('swal', ['icon'=>'success','title'=>'Eliminada','text'=>'Orden de compra eliminada.']);
        return redirect()->route('admin.purchase-orders.index');
    }

    // Cambiar estatus
    public function approve(PurchaseOrder $purchase_order)
    {
        if ($purchase_order->status !== 'draft') {
            return back()->with('swal',['icon'=>'error','title'=>'Error','text'=>'Solo borrador se puede aprobar.']);
        }
        $purchase_order->update(['status'=>'approved']);
        return back()->with('swal',['icon'=>'success','title'=>'Aprobada','text'=>'Orden aprobada.']);
    }

    public function cancel(PurchaseOrder $purchase_order)
    {
        if (!in_array($purchase_order->status, ['draft','approved'])) {
            return back()->with('swal',['icon'=>'error','title'=>'Error','text'=>'No se puede cancelar en este estado.']);
        }
        $purchase_order->update(['status'=>'cancelled']);
        return back()->with('swal',['icon'=>'success','title'=>'Cancelada','text'=>'Orden cancelada.']);
    }
}
