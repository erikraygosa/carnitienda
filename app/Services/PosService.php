<?php
// app/Services/PosService.php
namespace App\Services;

use App\Models\CashRegister;
use App\Models\PosSale;
use App\Models\PosSaleItem;
use Illuminate\Support\Facades\DB;

class PosService
{
  public function __construct(private CashService $cash, private StockService $stock) {}

  public function createSale(CashRegister $reg, array $data, array $items): PosSale
  {
    return DB::transaction(function () use ($reg,$data,$items) {
      $subtotal=$descuento=$impuestos=$total=0;
      foreach ($items as $it) {
        $line = ($it['cantidad']*$it['precio_unitario']) - ($it['descuento'] ?? 0);
        $tax  = $it['impuestos'] ?? 0;
        $subtotal += ($it['cantidad']*$it['precio_unitario']);
        $descuento += ($it['descuento'] ?? 0);
        $impuestos += $tax;
        $total     += ($line + $tax);
      }

      $sale = PosSale::create([
        'cash_register_id'=>$reg->id,
        'warehouse_id'    =>$reg->warehouse_id,
        'user_id'         =>$reg->user_id,
        'client_id'       =>$data['client_id'] ?? null,
        'fecha'           =>$data['fecha'] ?? now(),
        'subtotal'        =>$subtotal,
        'descuento'       =>$descuento,
        'impuestos'       =>$impuestos,
        'total'           =>$total,
        'metodo_pago'     =>$data['metodo_pago'] ?? 'EFECTIVO',
        'efectivo'        =>$data['efectivo'] ?? 0,
        'cambio'          =>$data['cambio'] ?? 0,
        'referencia'      =>$data['referencia'] ?? null,
      ]);

      foreach ($items as $it) {
        PosSaleItem::create([
          'pos_sale_id'=>$sale->id,
          'product_id'=>$it['product_id'],
          'cantidad'=>$it['cantidad'],
          'precio_unitario'=>$it['precio_unitario'],
          'descuento'=>$it['descuento'] ?? 0,
          'impuestos'=>$it['impuestos'] ?? 0,
          'importe'=>($it['cantidad']*$it['precio_unitario']) - ($it['descuento'] ?? 0) + ($it['impuestos'] ?? 0),
        ]);
        $this->stock->out($reg->warehouse_id, $it['product_id'], $it['cantidad'], 'Venta POS', $sale);
      }

      if (($data['metodo_pago'] ?? 'EFECTIVO') === 'EFECTIVO') {
        $this->cash->registerCashSale($reg, $total);
      }

      return $sale;
    });
  }
}
