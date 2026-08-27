<?php

namespace App\Services;

use App\Models\Client;
use App\Models\ClientAlias;
use App\Models\ClientPriceOverride;
use App\Models\PriceListItem;
use App\Models\Product;
use App\Models\ProductAlias;
use App\Models\SalesOrder;
use App\Models\SalesOrderItem;
use App\Models\SystemSetting;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Resuelve cliente/producto contra el catálogo real (por nombre o alias) y
 * crea/confirma/cancela pedidos en BORRADOR a partir de lo que interpretó el
 * chat de asistencia. Pensado para ser reutilizado también por el futuro bot
 * de WhatsApp (fase 2) — aquí solo vive la lógica de negocio, no el canal.
 */
class OrderAssistantService
{
    private const UMBRAL_CONFIANZA = 0.85;
    private const UMBRAL_MINIMO    = 0.55;

    /**
     * Busca un cliente por nombre/apodo. Devuelve:
     * - ['status' => 'found', 'client_id' => int, 'nombre' => string]
     * - ['status' => 'ambiguous', 'candidates' => [['id','nombre','score'], ...]]
     * - ['status' => 'not_found']
     */
    public function resolveClient(string $query): array
    {
        $query = trim($query);
        if ($query === '') {
            return ['status' => 'not_found', 'candidates' => []];
        }

        $alias = ClientAlias::whereRaw('LOWER(alias) = ?', [$this->normalize($query)])
            ->with('client')
            ->first();
        if ($alias && $alias->client) {
            return ['status' => 'found', 'client_id' => $alias->client_id, 'nombre' => $alias->client->nombre];
        }

        $candidates = Client::where('activo', true)->pluck('nombre', 'id')->all();
        $matches    = $this->bestMatches($query, $candidates);

        if (empty($matches)) {
            return ['status' => 'not_found', 'candidates' => []];
        }

        if (count($matches) === 1 || $matches[0]['score'] >= self::UMBRAL_CONFIANZA) {
            $top = $matches[0];
            ClientAlias::firstOrCreate(['client_id' => $top['id'], 'alias' => $this->normalize($query)]);
            return ['status' => 'found', 'client_id' => $top['id'], 'nombre' => $top['nombre']];
        }

        return ['status' => 'ambiguous', 'candidates' => $matches];
    }

    /**
     * Busca un producto por nombre/corte coloquial. Mismo formato de respuesta
     * que resolveClient().
     */
    public function resolveProduct(string $query): array
    {
        $query = trim($query);
        if ($query === '') {
            return ['status' => 'not_found', 'candidates' => []];
        }

        $alias = ProductAlias::whereRaw('LOWER(alias) = ?', [$this->normalize($query)])
            ->with('product')
            ->first();
        if ($alias && $alias->product) {
            return ['status' => 'found', 'product_id' => $alias->product_id, 'nombre' => $alias->product->nombre];
        }

        $candidates = Product::where('activo', true)->pluck('nombre', 'id')->all();
        $matches    = $this->bestMatches($query, $candidates);

        if (empty($matches)) {
            return ['status' => 'not_found', 'candidates' => []];
        }

        if (count($matches) === 1 || $matches[0]['score'] >= self::UMBRAL_CONFIANZA) {
            $top = $matches[0];
            ProductAlias::firstOrCreate(['product_id' => $top['id'], 'alias' => $this->normalize($query)]);
            return ['status' => 'found', 'product_id' => $top['id'], 'nombre' => $top['nombre']];
        }

        return ['status' => 'ambiguous', 'candidates' => $matches];
    }

    /**
     * Crea un SalesOrder en BORRADOR a partir de un cliente e items YA
     * resueltos (con product_id real). No adivina nada: si falta un
     * product_id, se rechaza en vez de crear una línea a medias.
     *
     * @param array{client_id: ?int} $client
     * @param array<int, array{product_id: int, cantidad: float}> $items
     */
    public function createDraft(array $client, array $items, User $user, ?int $conversationId = null): array
    {
        if (! $user->can('crear pedidos')) {
            return ['ok' => false, 'message' => 'No tienes permiso para crear pedidos en el sistema.'];
        }

        if (empty($items)) {
            return ['ok' => false, 'message' => 'No se especificó ningún producto para el pedido.'];
        }

        $clientModel = ! empty($client['client_id']) ? Client::find($client['client_id']) : null;

        $lineItems = [];
        foreach ($items as $it) {
            $productId = $it['product_id'] ?? null;
            $cantidad  = (float) ($it['cantidad'] ?? 0);

            if (! $productId || $cantidad <= 0) {
                return ['ok' => false, 'message' => 'Hay un producto o cantidad sin resolver correctamente. Usa buscar_producto primero.'];
            }

            $product = Product::find($productId);
            if (! $product) {
                return ['ok' => false, 'message' => "El producto con id {$productId} no existe."];
            }

            $lineItems[] = [
                'product_id'  => $product->id,
                'descripcion' => $product->nombre,
                'cantidad'    => $cantidad,
                'precio'      => $this->resolvePrecio($clientModel, $product),
            ];
        }

        // Ojo: SystemSetting::get() con tipo 'integer' devuelve (int) '' = 0
        // cuando el setting existe pero quedó en blanco — 0 es falsy pero no
        // null, así que aquí hace falta "?:" (no "??") para caer bien al
        // almacén principal/primero cuando no hay override configurado.
        $warehouseId = SystemSetting::get('pedidos.asistente_almacen_id')
            ?: Warehouse::where('is_primary', true)->value('id')
            ?: Warehouse::orderBy('id')->value('id');

        if (! $warehouseId) {
            return ['ok' => false, 'message' => 'No hay almacenes configurados en el sistema; no se puede crear el pedido.'];
        }

        $deliveryType = ($clientModel && $clientModel->shipping_route_id) ? 'ENVIO' : 'RECOGER';

        $order = null;

        DB::transaction(function () use (&$order, $clientModel, $lineItems, $warehouseId, $deliveryType, $user, $conversationId) {
            $subtotal = 0.0;
            foreach ($lineItems as $it) {
                $subtotal += $it['cantidad'] * $it['precio'];
            }

            $order = SalesOrder::create([
                'client_id'                 => $clientModel?->id,
                'warehouse_id'              => $warehouseId,
                'price_list_id'             => $clientModel?->price_list_id,
                'folio'                     => 'TEMP-' . uniqid(),
                'fecha'                     => now(),
                'delivery_type'             => $deliveryType,
                'shipping_route_id'         => $clientModel?->shipping_route_id,
                'payment_method'            => SalesOrder::PM_CONTRAENTREGA,
                'moneda'                    => 'MXN',
                'subtotal'                  => $subtotal,
                'impuestos'                 => 0,
                'descuento'                 => 0,
                'total'                     => $subtotal,
                'status'                    => SalesOrder::S_BORRADOR,
                'created_by'                => $user->id,
                'owner_id'                  => $user->id,
                'origen'                    => 'chat_asistente',
                'assistant_conversation_id' => $conversationId,
                'contraentrega_total'       => $subtotal,
                'comentarios'               => 'Pedido creado desde el chat de asistencia. Revisa almacén, entrega y forma de pago antes de aprobar.',
            ]);

            $order->updateQuietly([
                'folio' => 'SO-' . now()->format('Ymd') . '-' . Str::padLeft((string) $order->id, 4, '0'),
            ]);

            foreach ($lineItems as $it) {
                $lineTotal = $it['cantidad'] * $it['precio'];
                SalesOrderItem::create([
                    'sales_order_id' => $order->id,
                    'product_id'     => $it['product_id'],
                    'descripcion'    => $it['descripcion'],
                    'cantidad'       => $it['cantidad'],
                    'precio'         => $it['precio'],
                    'descuento'      => 0,
                    'impuesto'       => 0,
                    'total'          => $lineTotal,
                ]);
            }
        });

        return ['ok' => true, 'order' => $order->fresh(['items.product', 'client'])];
    }

    public function confirm(SalesOrder $order, User $user): array
    {
        if (! $user->can('crear pedidos')) {
            return ['ok' => false, 'message' => 'No tienes permiso para confirmar este pedido.'];
        }

        if ($order->status !== SalesOrder::S_BORRADOR) {
            return ['ok' => false, 'message' => 'Este pedido ya no está en borrador.'];
        }

        $order->update([
            'comentarios' => trim(($order->comentarios ?? '') . "\nConfirmado por {$user->name} vía chat de asistencia."),
        ]);

        return [
            'ok'      => true,
            'message' => "Pedido {$order->folio} confirmado. Queda como borrador para que un encargado lo apruebe en el sistema.",
        ];
    }

    public function cancel(SalesOrder $order, User $user): array
    {
        if (! $user->can('crear pedidos')) {
            return ['ok' => false, 'message' => 'No tienes permiso para cancelar este pedido.'];
        }

        if ($order->status !== SalesOrder::S_BORRADOR) {
            return ['ok' => false, 'message' => 'Solo se puede descartar un pedido que sigue en borrador.'];
        }

        $order->items()->delete();
        $order->delete();

        return ['ok' => true, 'message' => 'Se descartó el borrador del pedido.'];
    }

    private function resolvePrecio(?Client $client, Product $product): float
    {
        if ($client) {
            $override = ClientPriceOverride::where('client_id', $client->id)
                ->where('product_id', $product->id)
                ->value('precio');
            if ($override !== null) {
                return (float) $override;
            }

            if ($client->price_list_id) {
                $precioLista = PriceListItem::where('price_list_id', $client->price_list_id)
                    ->where('product_id', $product->id)
                    ->value('precio');
                if ($precioLista !== null) {
                    return (float) $precioLista;
                }
            }
        }

        return (float) $product->precio_base;
    }

    private function normalize(string $s): string
    {
        $s = mb_strtolower(trim($s));
        $s = strtr($s, ['á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ñ' => 'n', 'ü' => 'u']);
        return preg_replace('/\s+/', ' ', $s);
    }

    /**
     * @param array<int, string> $candidates id => nombre
     * @return array<int, array{id: int, nombre: string, score: float}>
     */
    private function bestMatches(string $query, array $candidates, int $limit = 3): array
    {
        $q = $this->normalize($query);
        $scored = [];

        foreach ($candidates as $id => $nombre) {
            $n = $this->normalize((string) $nombre);
            similar_text($q, $n, $pct);
            $score = $pct / 100;

            if ($n !== '' && (str_contains($n, $q) || str_contains($q, $n))) {
                $score = max($score, 0.9);
            }

            if ($score >= self::UMBRAL_MINIMO) {
                $scored[] = ['id' => (int) $id, 'nombre' => (string) $nombre, 'score' => round($score, 2)];
            }
        }

        usort($scored, fn ($a, $b) => $b['score'] <=> $a['score']);

        return array_slice($scored, 0, $limit);
    }
}
