<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Sale extends Model
{
    // === Constantes de estado ===
    public const S_BORRADOR     = 'BORRADOR';
    public const S_APROBADO     = 'APROBADO';
    public const S_PREPARANDO   = 'PREPARANDO';
    public const S_PROCESADO    = 'PROCESADO';
    public const S_EN_RUTA      = 'EN_RUTA';
    public const S_ENTREGADO    = 'ENTREGADO';
    public const S_NO_ENTREGADO = 'NO_ENTREGADO';
    public const S_CANCELADO    = 'CANCELADO';

    // === Métodos de pago (útiles en lógica) ===
    public const PM_CREDITO       = 'CREDITO';
    public const PM_TRANSFERENCIA = 'TRANSFERENCIA';
    public const PM_CONTRAENTREGA = 'CONTRAENTREGA';
    public const PM_EFECTIVO      = 'EFECTIVO';

    protected $fillable = [
        // originales
        'fecha','pos_register_id','warehouse_id','client_id','payment_type_id',
        'tipo_venta','subtotal','impuestos','descuento','total','status',
        'driver_id','user_id',
        // entrega/CFDI que ya agregaste
        'folio','moneda','price_list_id','credit_days',
        'delivery_type','entrega_nombre','entrega_telefono','entrega_calle','entrega_numero',
        'entrega_colonia','entrega_ciudad','entrega_estado','entrega_cp',
        'shipping_route_id',
        'cfdi_uuid','cfdi_xml_path','cfdi_pdf_path','stamped_at','canceled_at',
        // NUEVOS: logística y cobranza chofer (igual que SalesOrder)
        'preparado_at','despachado_at','en_ruta_at','entregado_at','no_entregado_at',
        'delivery_attempts','delivery_notes',
        'contraentrega_total','cobrado_efectivo','cobrado_confirmado_at','cobrado_confirmado_por',
        'driver_settlement_status','driver_settlement_at',
    ];

    protected $casts = [
        'fecha'                  => 'datetime',
        'stamped_at'             => 'datetime',
        'canceled_at'            => 'datetime',
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
    public function items(): HasMany                { return $this->hasMany(SaleItem::class); }
    public function client(): BelongsTo             { return $this->belongsTo(Client::class); }
    public function warehouse(): BelongsTo          { return $this->belongsTo(Warehouse::class); }
    public function driver(): BelongsTo             { return $this->belongsTo(Driver::class); }
    public function route(): BelongsTo              { return $this->belongsTo(ShippingRoute::class, 'shipping_route_id'); }
    public function priceList(): BelongsTo          { return $this->belongsTo(PriceList::class, 'price_list_id'); }
    public function posRegister(): BelongsTo        { return $this->belongsTo(PosRegister::class); }
    public function paymentType(): BelongsTo        { return $this->belongsTo(PaymentType::class, 'payment_type_id'); }
    public function cobradoConfirmadoPor(): BelongsTo { return $this->belongsTo(User::class,'cobrado_confirmado_por'); }

    // === Etiquetas de estado (opcional para UI) ===
    public function getStatusLabelAttribute(): string
    {
        return [
            self::S_BORRADOR     => 'Borrador',
            self::S_APROBADO     => 'Aprobado',
            self::S_PREPARANDO   => 'Preparando',
            self::S_PROCESADO    => 'Procesado',
            self::S_EN_RUTA      => 'En ruta',
            self::S_ENTREGADO    => 'Entregado',
            self::S_NO_ENTREGADO => 'No entregado',
            self::S_CANCELADO    => 'Cancelado',
        ][$this->status] ?? $this->status;
    }

    // === Helpers de negocio ===
    public function isContraentrega(): bool
    {
        // si usas payment_type_id -> PaymentType, puedes mapear a método; aquí usamos delivery/payment_method estilo SalesOrder
        return ($this->payment_method ?? null) === self::PM_CONTRAENTREGA
            || (strtoupper((string)$this->tipo_venta) === self::PM_CONTRAENTREGA);
    }

    public function getPorCobrarAttribute(): float
    {
        $esperado = (float)($this->contraentrega_total ?? 0);
        $cobrado  = (float)($this->cobrado_efectivo ?? 0);
        return max(0, $esperado - $cobrado);
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
        if ($fecha)    $q->whereDate('fecha', $fecha); // o programado_para si lo agregas en sales

        return $q->where('driver_settlement_status', 'PENDIENTE')
                 ->whereIn('status', [self::S_EN_RUTA, self::S_ENTREGADO, self::S_NO_ENTREGADO])
                 // si en sales determinas contraentrega con otro campo, ajusta este filtro:
                 ->where(function ($qq) {
                     $qq->where('tipo_venta', self::PM_CONTRAENTREGA)
                        ->orWhere('payment_method', self::PM_CONTRAENTREGA);
                 });
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

    // === Transiciones rápidas (si no usas un Service)
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
            'cobrado_efectivo'       => round(($this->cobrado_efectivo ?? 0) + $monto, 2),
            'cobrado_confirmado_at'  => now(),
            'cobrado_confirmado_por' => auth()->id(),
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
