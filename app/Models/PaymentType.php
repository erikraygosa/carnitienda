<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PaymentType extends Model
{
    protected $fillable = ['clave','descripcion','activo'];
    protected $casts = ['activo' => 'boolean'];

    /**
     * `clave` aquí es el identificador interno del sistema (EFECTIVO,
     * TRANSFERENCIA, CREDITO, CONTRAENTREGA...), NO el código del catálogo
     * SAT c_FormaPago. Este mapeo traduce uno a otro para CFDI.
     */
    protected const SAT_FORMA_PAGO_MAP = [
        'EFECTIVO'      => '01', // Efectivo
        'TRANSFERENCIA' => '03', // Transferencia electrónica de fondos
        'CONTRAENTREGA' => '01', // Se cobra en efectivo/tarjeta al entregar; sin dato exacto -> más común
        'CREDITO'       => '99', // No es una forma de pago real (es plazo) -> Por definir
    ];

    public function satFormaPago(): string
    {
        return self::SAT_FORMA_PAGO_MAP[$this->clave] ?? '99';
    }
}
