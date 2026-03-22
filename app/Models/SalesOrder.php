<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Traits\BelongsToCompany;

class SalesOrder extends Model
{
    use BelongsToCompany;
    // === Constantes de estado ===
    public const S_BORRADOR     = 'BORRADOR';
    public const S_APROBADO     = 'APROBADO';
    public const S_PREPARANDO   = 'PREPARANDO';
    public const S_PROCESADO    = 'PROCESADO';
    public const S_EN_RUTA      = 'EN_RUTA';
    public const S_DESPACHADO   = 'DESPACHADO'; // opcional en tu flujo
    public const S_ENTREGADO    = 'ENTREGADO';
    public const S_NO_ENTREGADO = 'NO_ENTREGADO';
    public const S_CANCELADO    = 'CANCELADO';

    // === Métodos de pago (por si los usas en lógica) ===
    public const PM_CREDITO       = 'CREDITO';
    public const PM_TRANSFERENCIA = 'TRANSFERENCIA';
    public const PM_CONTRAENTREGA = 'CONTRAENTREGA';
    public const PM_EFECTIVO      = 'EFECTIVO';

    protected $fillable = [
        'client_id','warehouse_id','price_list_id','quote_id','folio','fecha','programado_para',
        'delivery_type','entrega_nombre','entrega_telefono','entrega_calle','entrega_numero',
        'entrega_colonia','entrega_ciudad','entrega_estado','entrega_cp',
        'shipping_route_id','driver_id','payment_method','credit_days',
        'moneda','subtotal','impuestos','descuento','total','status',
        // nuevos (logística)
        'preparado_at','despachado_at','en_ruta_at','entregado_at','no_entregado_at',
        'delivery_attempts','delivery_notes',
        // cobranza chofer
        'contraentrega_total','cobrado_efectivo','cobrado_confirmado_at','cobrado_confirmado_por',
        // liquidación chofer
        'driver_settlement_status','driver_settlement_at','pos_register_id',
        // auditoría
        'created_by','owner_id',
    ];

    protected $casts = [
        'fecha'                  => 'datetime',
        'programado_para'        => 'date',
        'preparado_at'           => 'datetime',
        'despachado_at'          => 'datetime',
        'en_ruta_at'             => 'datetime',
        'entregado_at'           => 'datetime',
        'no_entregado_at'        => 'datetime',
        'cobrado_confirmado_at'  => 'datetime',
        'driver_settlement_at'   => 'datetime',
        'subtotal'               => 'decimal:2',
        'impuestos'              => 'decimal:2',
        'descuento'              => 'decimal:2',
        'total'                  => 'decimal:2',
        'contraentrega_total'    => 'decimal:2',
        'cobrado_efectivo'       => 'decimal:2',
        'delivery_attempts'      => 'integer',
    ];

    // === Relaciones ===
    public function quote(): BelongsTo          { return $this->belongsTo(Quote::class); }
    public function client(): BelongsTo         { return $this->belongsTo(Client::class); }
    public function warehouse(): BelongsTo      { return $this->belongsTo(Warehouse::class); }
    public function priceList(): BelongsTo      { return $this->belongsTo(PriceList::class); }
    public function driver(): BelongsTo         { return $this->belongsTo(Driver::class); }
    public function route(): BelongsTo          { return $this->belongsTo(ShippingRoute::class,'shipping_route_id'); }
    public function items(): HasMany            { return $this->hasMany(SalesOrderItem::class); }
    public function cobradoConfirmadoPor(): BelongsTo { return $this->belongsTo(User::class,'cobrado_confirmado_por'); }
    public function posRegister(): BelongsTo    { return $this->belongsTo(PosRegister::class,'pos_register_id'); }

    // === Etiquetas de estado ===
    public function getStatusLabelAttribute(): string
    {
        return [
            'BORRADOR'     => 'Borrador',
            'APROBADO'     => 'Aprobado',
            'PREPARANDO'   => 'Preparando',
            'PROCESADO'    => 'Procesado',
            'DESPACHADO'   => 'Despachado',
            'EN_RUTA'      => 'En ruta',
            'ENTREGADO'    => 'Entregado',
            'NO_ENTREGADO' => 'No entregado',
            'CANCELADO'    => 'Cancelado',
        ][$this->status] ?? $this->status;
    }


    // === Helpers de negocio ===
    public function isContraentrega(): bool
    {
        return $this->payment_method === self::PM_CONTRAENTREGA;
    }

    public function getPorCobrarAttribute(): float
    {
        // pendiente por cobrar (para contraentrega)
        $esperado = $this->contraentrega_total ?: 0.0;
        return max(0, (float)$esperado - (float)($this->cobrado_efectivo ?: 0.0));
    }

    public function getEstaLiquidadoAttribute(): bool
    {
        return ($this->driver_settlement_status ?? 'PENDIENTE') === 'LIQUIDADO';
    }

    // === Scopes útiles ===
    public function scopeEnRuta($q)
    {
        return $q->where('status', self::S_EN_RUTA);
    }

    public function scopeParaLiquidacionChofer($q, $driverId = null, $fecha = null)
    {
        if ($driverId) $q->where('driver_id', $driverId);
        if ($fecha)    $q->whereDate('programado_para', $fecha);

        return $q->where('payment_method', self::PM_CONTRAENTREGA)
                 ->where('driver_settlement_status', 'PENDIENTE')
                 ->whereIn('status', [self::S_EN_RUTA, self::S_ENTREGADO, self::S_NO_ENTREGADO]);
    }

    public function scopeDelDia($q, $fecha = null)
    {
        $fecha = $fecha ?: now()->toDateString();
        return $q->whereDate('programado_para', $fecha);
    }

    public function scopeBuscar($q, ?string $term = null)
    {
        if (!$term) return $q;
        $like = '%'.trim($term).'%';
        return $q->where(function ($qq) use ($like) {
            $qq->where('folio', 'like', $like)
               ->orWhereHas('client', fn($c) => $c->where('nombre','like',$like));
        });
    }

    // === Transiciones simples (opcionales si no usas el Service) ===
    public function marcarAprobado(): void      { $this->update(['status'=>self::S_APROBADO]); }
    public function marcarPreparando(): void    { $this->update(['status'=>self::S_PREPARANDO, 'preparado_at'=>now()]); }
    public function marcarProcesado(): void     { $this->update(['status'=>self::S_PROCESADO,  'despachado_at'=>now()]); }
    public function marcarEnRuta(): void        { $this->update(['status'=>self::S_EN_RUTA,    'en_ruta_at'=>now()]); }
    public function marcarEntregado(): void     { $this->update(['status'=>self::S_ENTREGADO,  'entregado_at'=>now()]); }
    public function marcarNoEntregado(?string $nota = null): void
    {
        $data = ['status'=>self::S_NO_ENTREGADO, 'no_entregado_at'=>now()];
        if ($nota) $data['delivery_notes'] = trim(($this->delivery_notes ?? '')."\n".$nota);
        $this->update($data);
        $this->increment('delivery_attempts');
    }
    public function marcarCancelado(?string $nota = null): void
    {
        $data = ['status'=>self::S_CANCELADO];
        if ($nota) $data['delivery_notes'] = trim(($this->delivery_notes ?? '')."\nCANCELADO: ".$nota);
        $this->update($data);
    }

    public function registrarCobroEfectivo(float $monto): void
    {
        $this->update([
            'cobrado_efectivo'      => round(($this->cobrado_efectivo ?? 0) + $monto, 2),
            'cobrado_confirmado_at' => now(),
            'cobrado_confirmado_por'=> auth()->id(),
        ]);
    }

    public function liquidarChofer(?int $posRegisterId = null): void
    {
        $data = [
            'driver_settlement_status' => 'LIQUIDADO',
            'driver_settlement_at'     => now(),
        ];
        if ($posRegisterId) $data['pos_register_id'] = $posRegisterId;
        $this->update($data);
    }

    
}
