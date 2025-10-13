<div class="flex items-center space-x-3">
    <a href="{{ $costUrl }}" class="text-indigo-600 hover:underline">Ver costos</a>
    <a href="{{ route('admin.stock.adjustments.create', ['warehouse_id'=>$warehouse_id, 'product_id'=>$product_id]) }}"
       class="text-amber-600 hover:underline">Ajuste</a>
    <a href="{{ route('admin.stock.transfers.create', ['from_warehouse_id'=>$warehouse_id, 'product_id'=>$product_id]) }}"
       class="text-emerald-700 hover:underline">Transferir</a>
</div>
