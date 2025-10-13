<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class StockController extends Controller
{
    public function index(Request $request)
    {
        return view('admin.stock.index', [
            'warehouses' => \App\Models\Warehouse::orderBy('nombre')->get(),
            'products'   => \App\Models\Product::orderBy('nombre')->get(['id','nombre']),
        ]);
    }

    public function costs(Request $request)
    {
        $data = $request->validate([
            'warehouse_id' => ['required','exists:warehouses,id'],
            'product_id'   => ['required','exists:products,id'],
        ]);

        $purchases = \App\Models\PurchaseItem::query()
            ->select('purchase_items.*')
            ->with(['purchase.provider'])
            ->where('product_id', $data['product_id'])
            ->whereHas('purchase', function($q) use ($data) {
                $q->where('warehouse_id', $data['warehouse_id'])
                  ->where('status','received');
            })
            ->latest('id')->limit(50)->get();

        return view('admin.stock.costs', [
            'purchases' => $purchases,
            'warehouse' => \App\Models\Warehouse::find($data['warehouse_id']),
            'product'   => \App\Models\Product::find($data['product_id']),
        ]);
    }
}
