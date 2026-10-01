<?php

namespace App\Services;

use App\Models\Dispatch;
use App\Models\DispatchItem;
use App\Models\Driver;
use App\Models\SalesOrder;
use App\Models\SystemSetting;
use App\Models\Warehouse;
use Illuminate\Support\Facades\DB;

/**
 * Asigna un pedido PROCESADO a su despacho de ruta automáticamente, cuando
 * Superadmin → Configuración → Despachos tiene "Rutas automáticas" activo
 * (despacho.modo_rutas = automatico). En modo "manual" (default) esta clase
 * no hace nada — el flujo sigue siendo el de siempre (crear/agregar a mano
 * desde /admin/dispatches o el panel de rutas).
 */
class AutoDespachoService
{
    public function __construct(private DocumentLogService $log) {}

    public function modoAutomatico(): bool
    {
        return SystemSetting::get('despacho.modo_rutas', 'manual') === 'automatico';
    }

    /**
     * Si el pedido ya trae ruta + ronda + fecha programada, lo mete (o
     * reubica) en el despacho PLANEADO de esa ruta+ronda+día, creándolo si
     * no existe — mismo criterio de "encontrar o crear" que usa
     * DispatchController::store() al crear uno a mano. Si al pedido le
     * falta cualquiera de esos tres datos, no se toca: queda "suelto" para
     * asignarse a mano desde el panel (igual que pedía el cambio).
     */
    public function asignarSiAplica(SalesOrder $order): void
    {
        if (! $this->modoAutomatico()) {
            return;
        }

        if (! $order->shipping_route_id || ! $order->ronda) {
            return;
        }

        $fecha = $order->programado_para ?: $order->fecha;
        if (! $fecha) {
            return;
        }

        DB::transaction(function () use ($order, $fecha) {
            $dispatch = $this->encontrarOCrearDespacho($order->shipping_route_id, $order->ronda, $fecha, "Auto-creado al procesar pedido {$order->folio}");

            $itemExistente    = DispatchItem::where('sales_order_id', $order->id)->first();
            $dispatchOrigenId = $itemExistente?->dispatch_id;

            if ($dispatchOrigenId === $dispatch->id) {
                return; // ya estaba en el despacho correcto
            }

            DispatchItem::updateOrCreate(
                ['sales_order_id' => $order->id],
                ['dispatch_id' => $dispatch->id, 'referencia' => $order->folio, 'status' => 'ASIGNADO']
            );

            $this->log->log($dispatch, 'PEDIDOS_AGREGADOS', null, null, null, "Pedido {$order->folio} auto-asignado");
        });
    }

    /**
     * Encuentra el despacho PLANEADO de esta ruta+ronda+día, o lo crea si no
     * existe — misma regla en los dos lugares que la necesitan: la
     * auto-asignación de arriba, y el panel de rutas (drag & drop) al soltar
     * un pedido en una celda que todavía no tiene despacho.
     */
    public function encontrarOCrearDespacho(int $routeId, int $ronda, $fecha, ?string $notaCreacion = null): Dispatch
    {
        $dispatch = Dispatch::where('shipping_route_id', $routeId)
            ->where('ronda', $ronda)
            ->whereDate('fecha', $fecha)
            ->where('status', 'PLANEADO')
            ->lockForUpdate()
            ->first();

        if ($dispatch) {
            return $dispatch;
        }

        $route  = \App\Models\ShippingRoute::find($routeId);
        $driver = Driver::firstOrCreate(
            ['nombre' => $route?->nombre ?? 'Sin nombre'],
            ['activo' => true]
        );

        $dispatch = Dispatch::create([
            'warehouse_id'      => $this->almacenDefault()?->id,
            'shipping_route_id' => $routeId,
            'ronda'             => $ronda,
            'driver_id'         => $driver->id,
            'fecha'             => $fecha,
            'status'            => 'PLANEADO',
        ]);

        $this->log->log($dispatch, 'CREADO', null, 'PLANEADO', null, $notaCreacion ?? 'Auto-creado');

        return $dispatch;
    }

    private function almacenDefault(): ?Warehouse
    {
        $id = SystemSetting::get('despacho.almacen_auto_id');
        if ($id) {
            $w = Warehouse::find($id);
            if ($w) return $w;
        }

        return Warehouse::where('is_primary', true)->first() ?? Warehouse::first();
    }
}
