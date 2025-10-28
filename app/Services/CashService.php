<?php

// app/Services/CashService.php
namespace App\Services;

use App\Models\CashRegister;
use App\Models\CashMovement;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class CashService
{
  public function open(int $warehouseId, int $userId, string $fecha, float $montoApertura=0, ?string $notas=null): CashRegister {
    return DB::transaction(function () use ($warehouseId,$userId,$fecha,$montoApertura,$notas) {
      return CashRegister::firstOrCreate(
        ['warehouse_id'=>$warehouseId,'user_id'=>$userId,'fecha'=>$fecha],
        ['monto_apertura'=>$montoApertura,'opened_at'=>now(),'estatus'=>'ABIERTO','notas'=>$notas]
      );
    });
  }

  public function addMovement(CashRegister $reg, string $tipo, float $monto, ?string $concepto=null, $source=null): CashMovement {
    return DB::transaction(function () use ($reg,$tipo,$monto,$concepto,$source) {
      $mov = new CashMovement(['tipo'=>$tipo,'monto'=>$monto,'concepto'=>$concepto,'created_by'=>Auth::id()]);
      if ($source) $mov->source()->associate($source);
      $reg->movements()->save($mov);

      if ($tipo==='INGRESO') $reg->ingresos += $monto; else $reg->egresos += $monto;
      $reg->monto_cierre = $reg->monto_apertura + $reg->ingresos - $reg->egresos + $reg->ventas_efectivo;
      $reg->save();

      return $mov;
    });
  }

  public function registerCashSale(CashRegister $reg, float $monto): void {
    $reg->ventas_efectivo += $monto;
    $reg->monto_cierre = $reg->monto_apertura + $reg->ingresos - $reg->egresos + $reg->ventas_efectivo;
    $reg->save();
  }

  public function close(CashRegister $reg): void {
    $reg->update(['estatus'=>'CERRADO','closed_at'=>now(),'closed_by'=>Auth::id()]);
  }
}
