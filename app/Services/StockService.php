<?php
// app/Services/StockService.php
namespace App\Services;

use App\Models\InventoryMovement;
use Illuminate\Support\Facades\Auth;

class StockService
{
  public function out(int $warehouseId, int $productId, float $cantidad, string $motivo, $source=null): InventoryMovement {
    $mov = new InventoryMovement([
      'warehouse_id'=>$warehouseId,'product_id'=>$productId,'tipo'=>'OUT','cantidad'=>$cantidad,'motivo'=>$motivo,'created_by'=>Auth::id()
    ]);
    if ($source) $mov->source()->associate($source);
    $mov->save();
    // aquí actualiza existencias reales si manejas tabla 'stocks'
    return $mov;
  }
}
