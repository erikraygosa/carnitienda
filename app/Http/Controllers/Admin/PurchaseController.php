<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Provider;
use App\Models\Purchase;
use App\Models\PurchaseItem;
use App\Models\PurchaseOrder;
use App\Models\Warehouse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PurchaseController extends Controller
{
        public function index()
    {
        $purchases = \App\Models\Purchase::with(['provider', 'warehouse'])
            ->orderBy('id', 'desc')
            ->get();
        return view('admin.purchases.index', compact('purchases'));
    }

    public function create(Request $request)
    {
        // Si viene de una OC aprobada, precarga items
        $fromOrder = null;
        $seedItems = [];

        if ($request->filled('purchase_order_id')) {
            $fromOrder = PurchaseOrder::with('items.product','provider','warehouse')
                ->findOrFail($request->integer('purchase_order_id'));

            // Solo permitimos prellenar si la OC está approved
            if ($fromOrder->status === 'approved') {
                foreach ($fromOrder->items as $it) {
                    $seedItems[] = [
                        'product_id'   => $it->product_id,
                        'qty_received' => (float)$it->qty_ordered,
                        'price'        => (float)$it->price,
                        'discount'     => (float)($it->discount ?? 0),
                        'tax_rate'     => (float)($it->tax_rate ?? 0),
                        'total'        => (float)$it->total,
                    ];
                }
            }
        }

        return view('admin.purchases.create', [
            'providers'   => Provider::orderBy('nombre')->get(),
            'warehouses'  => Warehouse::orderBy('nombre')->get(),
            'products'    => Product::orderBy('nombre')->get(['id','nombre']),
            'order'       => $fromOrder,
            'seedItems'   => $seedItems,
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'provider_id'        => ['required','exists:providers,id'],
            'warehouse_id'       => ['required','exists:warehouses,id'],
            'purchase_order_id'  => ['nullable','exists:purchase_orders,id'],
            'fecha'              => ['required','date'],
            'currency'           => ['required','string','max:10'],
            'notas'              => ['nullable','string'],
            'folio'              => ['nullable','string','max:50','unique:purchases,folio'],
            'payment_method' => ['required', 'string', 'max:50'],
            'items'                   => ['required','array','min:1'],
            'items.*.product_id'      => ['required','exists:products,id'],
            'items.*.qty_received'    => ['required','numeric','min:0.001'],
            'items.*.price'           => ['required','numeric','min:0'],
            'items.*.discount'        => ['nullable','numeric','min:0'],
            'items.*.tax_rate'        => ['nullable','numeric','min:0'],
        ]);

        // Folio autogenerado si viene vacío
        $folio = trim((string) $request->input('folio',''));
        if ($folio === '') {
            $seq = (Purchase::max('id') ?? 0) + 1;
            $folio = 'CMP-'.now()->format('Ymd').'-'.str_pad($seq, 4, '0', STR_PAD_LEFT);
        }

        $purchase = null;

        DB::transaction(function () use ($data, $folio, &$purchase) {
            // Totales
            $subtotal = 0; $discount_total = 0; $tax_total = 0; $grand = 0;

            foreach ($data['items'] as $it) {
                $line_sub  = $it['qty_received'] * $it['price'];
                $line_disc = $it['discount'] ?? 0;
                $base      = max($line_sub - $line_disc, 0);
                $line_tax  = ($it['tax_rate'] ?? 0) * 0.01 * $base;
                $line_total= $base + $line_tax;

                $subtotal       += $line_sub;
                $discount_total += $line_disc;
                $tax_total      += $line_tax;
                $grand          += $line_total;
            }

            $purchase = Purchase::create([
                'provider_id'       => $data['provider_id'],
                'warehouse_id'      => $data['warehouse_id'],
                'user_id'           => auth()->id(),
                'purchase_order_id' => $data['purchase_order_id'] ?? null,
                'folio'             => $folio,
                'fecha'             => $data['fecha'],
                'status'            => 'draft',
                'subtotal'          => $subtotal,
                'discount_total'    => $discount_total,
                'tax_total'         => $tax_total,
                'total'             => $grand,
                'currency'          => $data['currency'],
                'notas'             => $data['notas'] ?? null,
            ]);

            foreach ($data['items'] as $it) {
                $line_sub  = $it['qty_received'] * $it['price'];
                $line_disc = $it['discount'] ?? 0;
                $base      = max($line_sub - $line_disc, 0);
                $line_tax  = ($it['tax_rate'] ?? 0) * 0.01 * $base;
                $line_total= $base + $line_tax;

                PurchaseItem::create([
                    'purchase_id'   => $purchase->id,
                    'product_id'    => $it['product_id'],
                    'qty_received'  => $it['qty_received'],
                    'price'         => $it['price'],
                    'total'         => $line_total,
                ]);
            }
        });

        session()->flash('swal', ['icon'=>'success','title'=>'¡Bien Hecho!','text'=>'Compra creada.']);
        return redirect()->route('admin.purchases.edit', $purchase);
    }

    public function edit(Purchase $purchase)
    {
        return view('admin.purchases.edit', [
            'purchase'   => $purchase->load('items.product','provider','warehouse','order'),
            'providers'  => Provider::orderBy('nombre')->get(),
            'warehouses' => Warehouse::orderBy('nombre')->get(),
            'products'   => Product::orderBy('nombre')->get(['id','nombre']),
        ]);
    }

    public function update(Request $request, Purchase $purchase)
    {
        if ($purchase->status !== 'draft') {
            return back()->with('swal', ['icon'=>'error','title'=>'Error','text'=>'Solo compras en borrador pueden editarse.']);
        }

        $data = $request->validate([
            'provider_id'        => ['required','exists:providers,id'],
            'warehouse_id'       => ['required','exists:warehouses,id'],
            'purchase_order_id'  => ['nullable','exists:purchase_orders,id'],
            'fecha'              => ['required','date'],
            'currency'           => ['required','string','max:10'],
            'notas'              => ['nullable','string'],

            'items'                   => ['required','array','min:1'],
            'items.*.product_id'      => ['required','exists:products,id'],
            'items.*.qty_received'    => ['required','numeric','min:0.001'],
            'items.*.price'           => ['required','numeric','min:0'],
            'items.*.discount'        => ['nullable','numeric','min:0'],
            'items.*.tax_rate'        => ['nullable','numeric','min:0'],
        ]);

        DB::transaction(function () use ($purchase, $data) {
            $subtotal = 0; $discount_total = 0; $tax_total = 0; $grand = 0;

            foreach ($data['items'] as $it) {
                $line_sub  = $it['qty_received'] * $it['price'];
                $line_disc = $it['discount'] ?? 0;
                $base      = max($line_sub - $line_disc, 0);
                $line_tax  = ($it['tax_rate'] ?? 0) * 0.01 * $base;
                $line_total= $base + $line_tax;

                $subtotal       += $line_sub;
                $discount_total += $line_disc;
                $tax_total      += $line_tax;
                $grand          += $line_total;
            }

            $purchase->update([
                'provider_id'       => $data['provider_id'],
                'warehouse_id'      => $data['warehouse_id'],
                'purchase_order_id' => $data['purchase_order_id'] ?? null,
                'fecha'             => $data['fecha'],
                'subtotal'          => $subtotal,
                'discount_total'    => $discount_total,
                'tax_total'         => $tax_total,
                'total'             => $grand,
                'currency'          => $data['currency'],
                'notas'             => $data['notas'] ?? null,
            ]);

            $purchase->items()->delete();

            foreach ($data['items'] as $it) {
                $line_sub  = $it['qty_received'] * $it['price'];
                $line_disc = $it['discount'] ?? 0;
                $base      = max($line_sub - $line_disc, 0);
                $line_tax  = ($it['tax_rate'] ?? 0) * 0.01 * $base;
                $line_total= $base + $line_tax;

                PurchaseItem::create([
                    'purchase_id'   => $purchase->id,
                    'product_id'    => $it['product_id'],
                    'qty_received'  => $it['qty_received'],
                    'price'         => $it['price'],
                    'total'         => $line_total,
                ]);
            }
        });

        session()->flash('swal', ['icon'=>'success','title'=>'Actualizado','text'=>'Compra actualizada.']);
        return redirect()->route('admin.purchases.edit', $purchase);
    }

    public function destroy(Purchase $purchase)
    {
        if ($purchase->status !== 'draft') {
            return back()->with('swal',['icon'=>'error','title'=>'Error','text'=>'Solo borrador puede eliminarse.']);
        }

        $purchase->delete();
        return redirect()->route('admin.purchases.index')
            ->with('swal',['icon'=>'success','title'=>'Eliminada','text'=>'Compra eliminada.']);
    }

    // Marcar como recibida y sumar al almacén (stock_movements)
    public function receive(Purchase $purchase)
    {
        if ($purchase->status !== 'draft') {
            return back()->with('swal',['icon'=>'error','title'=>'Error','text'=>'Solo borrador puede recibirse.']);
        }

        DB::transaction(function () use ($purchase) {
            foreach ($purchase->items as $it) {
                DB::table('stock_movements')->insert([
                    'warehouse_id'    => $purchase->warehouse_id,
                    'product_id'      => $it->product_id,
                    'tipo'            => 'IN',
                    'cantidad'        => $it->qty_received,
                    'motivo'          => 'Compra',
                    'referencia_type' => \App\Models\Purchase::class,
                    'referencia_id'   => $purchase->id,
                    'user_id'         => auth()->id(),
                    'created_at'      => now(),
                    'updated_at'      => now(),
                ]);
            }

            $purchase->update(['status' => 'received']);
        });

        return back()->with('swal',['icon'=>'success','title'=>'Recibida','text'=>'Compra marcada como recibida y almacen actualizado.']);
    }

    public function cancel(Purchase $purchase)
    {
        if (!in_array($purchase->status, ['draft'])) {
            return back()->with('swal',['icon'=>'error','title'=>'Error','text'=>'No se puede cancelar en este estado.']);
        }

        $purchase->update(['status' => 'cancelled']);
        return back()->with('swal',['icon'=>'success','title'=>'Cancelada','text'=>'Compra cancelada.']);
    }
}
