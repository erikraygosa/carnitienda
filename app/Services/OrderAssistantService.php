<?php

namespace App\Services;

use App\Models\AssistantConversation;
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
use App\Services\DocumentLogService;
use Carbon\Carbon;
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

    public function __construct(private DocumentLogService $log) {}

    /**
     * Busca un cliente por nombre/apodo. Devuelve:
     * - ['status' => 'found', 'client_id' => int, 'nombre' => string]
     * - ['status' => 'ambiguous', 'candidates' => [['id','nombre','score'], ...]]
     * - ['status' => 'not_found']
     */
    public function resolveClient(string $query, ?int $conversationId = null): array
    {
        $query = trim($query);
        if ($query === '') {
            return ['status' => 'not_found', 'candidates' => []];
        }

        $candidates = Client::where('activo', true)->pluck('nombre', 'id')->all();
        $matches    = $this->bestMatches($query, $candidates);

        // Un alias guardado de una resolución anterior NO es un atajo ciego
        // — cuenta como una coincidencia perfecta más, así sigue pasando
        // por la misma detección de empate que cualquier otra búsqueda. Sin
        // esto, un alias guardado por error (ej. de antes de un fix al
        // algoritmo de matching) contestaría siempre primero y nunca
        // dejaría que una ambigüedad real (dos clientes distintos con el
        // mismo texto de búsqueda) se detecte.
        $alias = ClientAlias::whereRaw('LOWER(alias) = ?', [$this->normalize($query)])
            ->with('client')
            ->first();
        if ($alias && $alias->client && $alias->client->activo) {
            $matches = $this->mergeAliasMatch($matches, $alias->client_id, $alias->client->nombre);
        }

        if (empty($matches)) {
            return ['status' => 'not_found', 'candidates' => []];
        }

        // Se recuerdan TODOS los ids que salieron de esta búsqueda real
        // (el elegido, o los candidatos si quedó ambiguo) — createDraft()/
        // changeClient() solo aceptan un client_id que haya pasado por
        // aquí, para no confiar ciegamente en lo que "recuerde" el modelo
        // de IA de una lista que mostró en un turno anterior.
        $this->rememberResolved($conversationId, 'clients', array_column($matches, 'id'));

        if ($this->isClearWinner($matches)) {
            $top = $matches[0];
            ClientAlias::firstOrCreate(['client_id' => $top['id'], 'alias' => $this->normalize($query)]);
            return ['status' => 'found', 'client_id' => $top['id'], 'nombre' => $top['nombre']];
        }

        $this->rememberLastCandidates($conversationId, 'clients', $matches);

        return ['status' => 'ambiguous', 'candidates' => $matches];
    }

    /**
     * Mezcla el cliente/producto de un alias exacto en la lista de
     * coincidencias como un match perfecto (score 1.0), sin duplicar si ya
     * estaba, y reordena. Ver nota en resolveClient() sobre por qué el
     * alias ya no es un atajo directo.
     *
     * @param array<int, array{id: int, nombre: string, score: float}> $matches
     * @return array<int, array{id: int, nombre: string, score: float}>
     */
    private function mergeAliasMatch(array $matches, int $id, string $nombre): array
    {
        $existe = false;
        foreach ($matches as &$m) {
            if ($m['id'] === $id) {
                $m['score'] = 1.0;
                $existe = true;
                break;
            }
        }
        unset($m);

        if (! $existe) {
            $matches[] = ['id' => $id, 'nombre' => $nombre, 'score' => 1.0];
        }

        usort($matches, fn ($a, $b) => $b['score'] <=> $a['score']);

        return array_slice($matches, 0, 3);
    }

    /**
     * Guarda en la conversación qué ids de cliente/producto salieron de una
     * búsqueda real, para poder validar después que crear_borrador_pedido/
     * cambiar_cliente_pedido no manden un id que el modelo de IA se haya
     * inventado (o "recordado mal") en vez de resolver de verdad.
     */
    private function rememberResolved(?int $conversationId, string $tipo, array $ids): void
    {
        if (! $conversationId || empty($ids)) {
            return;
        }

        $conversation = AssistantConversation::find($conversationId);
        if (! $conversation) {
            return;
        }

        $ctx = $conversation->resolved_context ?? [];
        $ctx[$tipo] = array_values(array_unique(array_merge($ctx[$tipo] ?? [], $ids)));
        $conversation->update(['resolved_context' => $ctx]);
    }

    /**
     * ¿Este id de cliente/producto salió de una búsqueda real en esta
     * conversación? Sin conversationId (llamadas directas, tests, futura
     * integración de WhatsApp sin este candado) no se bloquea nada — el
     * candado es una capa extra, no la única validación.
     */
    private function wasResolved(?int $conversationId, string $tipo, int $id): bool
    {
        if (! $conversationId) {
            return true;
        }

        $conversation = AssistantConversation::find($conversationId);
        if (! $conversation) {
            return true;
        }

        $ids = $conversation->resolved_context[$tipo] ?? [];
        return in_array($id, $ids, true);
    }

    /**
     * Busca un producto por nombre/corte coloquial. Mismo formato de respuesta
     * que resolveClient().
     */
    public function resolveProduct(string $query, ?int $conversationId = null): array
    {
        $query = trim($query);
        if ($query === '') {
            return ['status' => 'not_found', 'candidates' => []];
        }

        $candidates = Product::where('activo', true)->pluck('nombre', 'id')->all();
        $matches    = $this->bestMatches($query, $candidates);

        $alias = ProductAlias::whereRaw('LOWER(alias) = ?', [$this->normalize($query)])
            ->with('product')
            ->first();
        if ($alias && $alias->product && $alias->product->activo) {
            $matches = $this->mergeAliasMatch($matches, $alias->product_id, $alias->product->nombre);
        }

        if (empty($matches)) {
            return ['status' => 'not_found', 'candidates' => []];
        }

        $this->rememberResolved($conversationId, 'products', array_column($matches, 'id'));

        if ($this->isClearWinner($matches)) {
            $top = $matches[0];
            ProductAlias::firstOrCreate(['product_id' => $top['id'], 'alias' => $this->normalize($query)]);
            return ['status' => 'found', 'product_id' => $top['id'], 'nombre' => $top['nombre']];
        }

        $this->rememberLastCandidates($conversationId, 'products', $matches);

        return ['status' => 'ambiguous', 'candidates' => $matches];
    }

    /**
     * Guarda la ÚLTIMA lista de candidatos mostrada como ambigua, en el
     * mismo orden en que se le presentó al usuario (1, 2, 3...). Se usa
     * para poder interpretar de forma determinista una respuesta que sea
     * solo un número ("2") sin depender de que el modelo de IA recuerde
     * bien su propia lista y vuelva a buscar por su cuenta — ver
     * AiChatService::numericSelectionHint().
     */
    private function rememberLastCandidates(?int $conversationId, string $tipo, array $matches): void
    {
        if (! $conversationId) {
            return;
        }

        $conversation = AssistantConversation::find($conversationId);
        if (! $conversation) {
            return;
        }

        $ctx = $conversation->resolved_context ?? [];
        $ctx['last_candidates'] = [
            'type'  => $tipo,
            'items' => array_map(fn ($m) => ['id' => $m['id'], 'nombre' => $m['nombre']], $matches),
        ];
        $conversation->update(['resolved_context' => $ctx]);
    }

    /**
     * Crea un SalesOrder en BORRADOR a partir de un cliente e items YA
     * resueltos (con product_id real). No adivina nada: si falta un
     * product_id, se rechaza en vez de crear una línea a medias.
     *
     * @param array{client_id: ?int} $client
     * @param array<int, array{product_id: int, cantidad: float, comentario?: ?string, presentacion?: ?string}> $items
     * @param string|null $programadoPara Fecha (cualquier formato reconocible por Carbon)
     *        que el usuario mencionó para el pedido, ya normalizada a texto por el
     *        modelo de IA — ej. "2026-08-28". Si viene vacía o no se puede
     *        interpretar, se usa el default (mañana).
     */
    public function createDraft(array $client, array $items, User $user, ?int $conversationId = null, ?string $programadoPara = null): array
    {
        if (! $user->can('crear pedidos')) {
            return ['ok' => false, 'message' => 'No tienes permiso para crear pedidos en el sistema.'];
        }

        if (empty($items)) {
            return ['ok' => false, 'message' => 'No se especificó ningún producto para el pedido.'];
        }

        // El client_id/product_id debe venir de una búsqueda real hecha en
        // ESTA conversación (buscar_cliente/buscar_producto) — nunca de lo
        // que el modelo de IA "recuerde" de un turno anterior. Sin este
        // candado, un id inventado o mal recordado crea el pedido con un
        // cliente/producto completamente distinto al que pidió el usuario,
        // sin ningún error visible.
        if (! empty($client['client_id']) && ! $this->wasResolved($conversationId, 'clients', (int) $client['client_id'])) {
            return ['ok' => false, 'message' => 'Ese cliente no viene de una búsqueda válida en esta conversación. Usa buscar_cliente de nuevo antes de crear el pedido.'];
        }

        $clientModel = ! empty($client['client_id']) ? Client::find($client['client_id']) : null;

        $lineItems = [];
        foreach ($items as $it) {
            $productId    = $it['product_id'] ?? null;
            $cantidad     = (float) ($it['cantidad'] ?? 0);
            $comentario   = trim((string) ($it['comentario'] ?? ''));
            $presentacion = strtoupper(trim((string) ($it['presentacion'] ?? '')));

            if (! $productId || $cantidad <= 0) {
                return ['ok' => false, 'message' => 'Hay un producto o cantidad sin resolver correctamente. Usa buscar_producto primero.'];
            }

            if (! $this->wasResolved($conversationId, 'products', (int) $productId)) {
                return ['ok' => false, 'message' => 'Uno de los productos no viene de una búsqueda válida en esta conversación. Usa buscar_producto de nuevo antes de crear el pedido.'];
            }

            $product = Product::find($productId);
            if (! $product) {
                return ['ok' => false, 'message' => "El producto con id {$productId} no existe."];
            }

            // Igual que en el formulario manual: solo estos 3 valores son
            // válidos en la columna presentacion — cualquier otra cosa que
            // mande el modelo se descarta. Y, también igual que el
            // formulario manual (que nace en "Kilos"), si no vino nada se
            // asume KILOS en vez de dejarlo en null — es el caso inmensamente
            // más común y así el pedido creado por el asistente queda
            // consistente con uno creado a mano.
            if (! in_array($presentacion, ['KILOS', 'PIEZAS', 'CAJAS'], true)) {
                $presentacion = 'KILOS';
            }

            // El texto que el usuario escribió entre paréntesis junto al
            // producto (ej. "milanesa de cerdo (descongelada)") no es parte
            // del nombre a buscar — es una nota de esa línea, se agrega tal
            // cual a la descripción del pedido.
            $descripcion = $comentario !== '' ? "{$product->nombre} ({$comentario})" : $product->nombre;

            $lineItems[] = [
                'product_id'   => $product->id,
                'descripcion'  => $descripcion,
                'cantidad'     => $cantidad,
                'presentacion' => $presentacion,
                'precio'       => $this->resolvePrecio($clientModel, $product),
            ];
        }

        $resolvedFecha = $this->resolveProgramadoPara($programadoPara);

        // Evita duplicar el pedido si el modelo de IA vuelve a llamar esta
        // herramienta para lo mismo dentro de la misma conversación — típico
        // cuando el usuario escribe "ok" otra vez después de ya haber
        // confirmado y el modelo, sin nada nuevo que hacer, reintenta crear
        // el pedido en vez de simplemente responder. Si ya existe un pedido
        // de esta conversación con el mismo cliente y las mismas líneas, se
        // regresa ese en vez de crear uno nuevo — pero si el usuario sí
        // mencionó una fecha distinta esta vez (ej. corrigió "es para el 29,
        // no el 28"), se actualiza el "Programado para" del pedido existente
        // en vez de ignorar la corrección o crear un duplicado.
        if ($conversationId) {
            $existing = SalesOrder::with('items')
                ->where('assistant_conversation_id', $conversationId)
                ->where('origen', 'chat_asistente')
                ->orderByDesc('id')
                ->first();

            if ($existing && (int) $existing->client_id === (int) ($clientModel?->id ?? 0) && $this->sameLines($existing->items, $lineItems)) {
                $fechaExistente = optional($existing->programado_para)->format('Y-m-d');

                if ($programadoPara !== null && $fechaExistente !== $resolvedFecha) {
                    $existing->update(['programado_para' => $resolvedFecha]);
                    return [
                        'ok'      => true,
                        'order'   => $existing->fresh(['items.product', 'client']),
                        'message' => "Ese pedido ya se había creado como {$existing->folio}, solo actualicé la fecha programada.",
                    ];
                }

                return [
                    'ok'      => true,
                    'order'   => $existing->fresh(['items.product', 'client']),
                    'message' => "Ese pedido ya se había creado como {$existing->folio}, no se duplicó.",
                ];
            }
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

        // Mismo default que el formulario manual de Crear pedido: a crédito.
        // Solo cae a contraentrega cuando no se pudo identificar un cliente
        // registrado (no se le puede dar crédito a alguien sin catálogo).
        $paymentMethod = $clientModel ? SalesOrder::PM_CREDITO : SalesOrder::PM_CONTRAENTREGA;
        $creditDays    = $clientModel ? (int) ($clientModel->credito_dias ?? 0) : null;

        $order = null;

        DB::transaction(function () use (&$order, $clientModel, $lineItems, $warehouseId, $deliveryType, $paymentMethod, $creditDays, $user, $conversationId, $resolvedFecha) {
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
                'programado_para'           => $resolvedFecha,
                'delivery_type'             => $deliveryType,
                'shipping_route_id'         => $clientModel?->shipping_route_id,
                'payment_method'            => $paymentMethod,
                'credit_days'               => $creditDays,
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
                'contraentrega_total'       => $paymentMethod === SalesOrder::PM_CONTRAENTREGA ? $subtotal : 0,
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
                    'presentacion'   => $it['presentacion'],
                    'precio'         => $it['precio'],
                    'descuento'      => 0,
                    'impuesto'       => 0,
                    'total'          => $lineTotal,
                ]);
            }
        });

        $this->log->log($order, 'CREADO', null, $order->status, $user->id, 'Creado desde el chat de asistencia.');

        return ['ok' => true, 'order' => $order->fresh(['items.product', 'client'])];
    }

    /**
     * Cambia el cliente de un pedido en borrador ya creado — para cuando la
     * resolución automática/fuzzy acertó mal o el usuario simplemente se
     * equivocó al dictarlo. Recalcula precios (por si el nuevo cliente tiene
     * lista de precios u overrides distintos), forma de pago/crédito y tipo
     * de entrega igual que si se hubiera creado el borrador con ese cliente
     * desde el principio.
     */
    public function changeClient(SalesOrder $order, int $newClientId, User $user): array
    {
        if (! $user->can('crear pedidos')) {
            return ['ok' => false, 'message' => 'No tienes permiso para modificar este pedido.'];
        }

        if ($order->status !== SalesOrder::S_BORRADOR) {
            return ['ok' => false, 'message' => 'Solo se puede cambiar el cliente de un pedido que sigue en borrador.'];
        }

        if (! $this->wasResolved($order->assistant_conversation_id, 'clients', $newClientId)) {
            return ['ok' => false, 'message' => 'Ese cliente no viene de una búsqueda válida en esta conversación. Usa buscar_cliente de nuevo antes de cambiarlo.'];
        }

        $client = Client::find($newClientId);
        if (! $client) {
            return ['ok' => false, 'message' => "El cliente con id {$newClientId} no existe."];
        }

        DB::transaction(function () use ($order, $client) {
            $order->loadMissing('items.product');

            $subtotal = 0.0;
            foreach ($order->items as $item) {
                if ($item->product) {
                    $item->precio = $this->resolvePrecio($client, $item->product);
                    $item->total  = max($item->cantidad * $item->precio - $item->descuento, 0) + $item->impuesto;
                    $item->save();
                }
                $subtotal += (float) $item->cantidad * (float) $item->precio;
            }

            $paymentMethod = SalesOrder::PM_CREDITO;

            $order->update([
                'client_id'           => $client->id,
                'price_list_id'       => $client->price_list_id,
                'shipping_route_id'   => $client->shipping_route_id,
                'delivery_type'       => $client->shipping_route_id ? 'ENVIO' : 'RECOGER',
                'payment_method'      => $paymentMethod,
                'credit_days'         => (int) ($client->credito_dias ?? 0),
                'subtotal'            => $subtotal,
                'total'               => $subtotal,
                'contraentrega_total' => 0,
            ]);
        });

        $this->log->log($order, 'EDITADO', null, null, $user->id, "Cliente cambiado a {$client->nombre} vía chat de asistencia.");

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

        $this->log->log($order, 'CONFIRMADO_ASISTENTE', null, null, $user->id, "Confirmado por {$user->name} vía chat de asistencia.");

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

        $this->log->log($order, 'CAMBIO_ESTADO', $order->status, 'CANCELADO', $user->id, 'Descartado desde el chat de asistencia.');

        $order->items()->delete();
        $order->delete();

        return ['ok' => true, 'message' => 'Se descartó el borrador del pedido.'];
    }

    /**
     * Compara las líneas de un pedido ya existente (colección de
     * SalesOrderItem) contra un array de líneas recién resueltas, sin
     * importar el orden — usado para detectar que el modelo de IA está
     * pidiendo crear "otra vez" exactamente el mismo pedido.
     *
     * Incluye la descripción en la comparación (no solo product_id/cantidad)
     * para que un comentario distinto entre paréntesis ("descongelada" vs
     * "en trozos") sí cuente como una línea diferente, en vez de perderse
     * silenciosamente contra un pedido previo idéntico salvo por la nota.
     *
     * @param iterable<SalesOrderItem> $existingItems
     * @param array<int, array{product_id: int, cantidad: float, descripcion: string}> $newLines
     */
    private function sameLines(iterable $existingItems, array $newLines): bool
    {
        $normalize = fn (int|float $productId, int|float $cantidad, string $descripcion, ?string $presentacion) =>
            $productId . ':' . rtrim(rtrim(number_format((float) $cantidad, 3, '.', ''), '0'), '.') . ':' . mb_strtolower(trim($descripcion)) . ':' . ($presentacion ?? '');

        $existingSig = collect($existingItems)
            ->map(fn ($i) => $normalize($i->product_id, $i->cantidad, $i->descripcion, $i->presentacion))
            ->sort()->values()->all();

        $newSig = collect($newLines)
            ->map(fn ($i) => $normalize($i['product_id'], $i['cantidad'], $i['descripcion'], $i['presentacion'] ?? null))
            ->sort()->values()->all();

        return $existingSig === $newSig;
    }

    /**
     * Interpreta la fecha que el modelo de IA extrajo del mensaje del
     * usuario (ej. "2026-08-28", "28/08/2026", "28-08-2026", "28/08",
     * "28 de agosto") y la normaliza a "Y-m-d" para "Programado para". Si no
     * viene fecha, no se puede interpretar, o cae en el pasado (típico de un
     * mal parseo), cae al mismo default que el formulario manual: mañana.
     */
    private function resolveProgramadoPara(?string $fecha): string
    {
        $fecha  = trim((string) $fecha);
        $parsed = $fecha !== '' ? $this->parseFechaFlexible($fecha) : null;

        if ($parsed && ($parsed->isToday() || $parsed->isFuture())) {
            return $parsed->format('Y-m-d');
        }

        return now()->addDay()->format('Y-m-d');
    }

    /**
     * Formatos numéricos con "/" o "-" se interpretan explícitamente como
     * día primero (convención en México/España) — Carbon::parse() por sí
     * solo asume mes primero para ese formato (estilo estadounidense) y
     * falla o interpreta mal fechas como "28/08/2026" (28 no es un mes
     * válido, así que ni siquiera cae en una fecha "razonable" equivocada,
     * directamente truena y el llamador cae al default). El parser genérico
     * de Carbon solo se usa como respaldo para fechas en texto ("28 de
     * agosto de 2026"), donde el mes viene escrito y no hay ambigüedad.
     */
    private function parseFechaFlexible(string $fecha): ?Carbon
    {
        // dd/mm/yyyy o dd-mm-yyyy (año de 2 o 4 dígitos)
        if (preg_match('/^(\d{1,2})[\/\-](\d{1,2})[\/\-](\d{2,4})$/', $fecha, $m)) {
            $anio = strlen($m[3]) === 2 ? ('20' . $m[3]) : $m[3];
            try {
                return Carbon::create((int) $anio, (int) $m[2], (int) $m[1])->startOfDay();
            } catch (\Throwable $e) {
                return null;
            }
        }

        // dd/mm o dd-mm sin año -> asume el año actual.
        if (preg_match('/^(\d{1,2})[\/\-](\d{1,2})$/', $fecha, $m)) {
            try {
                return Carbon::create(now()->year, (int) $m[2], (int) $m[1])->startOfDay();
            } catch (\Throwable $e) {
                return null;
            }
        }

        // yyyy-mm-dd (ISO — lo que normalmente manda el modelo de IA).
        if (preg_match('/^\d{4}-\d{1,2}-\d{1,2}/', $fecha)) {
            try {
                return Carbon::parse($fecha)->startOfDay();
            } catch (\Throwable $e) {
                return null;
            }
        }

        // Fechas en texto ("28 de agosto de 2026", "28 de agosto") — sin
        // ambigüedad de orden porque el mes viene escrito, pero PHP no
        // entiende nombres de mes en español de forma nativa, así que se
        // traducen antes de pasarlas al parser genérico de Carbon (que sí
        // entiende "28 august 2026").
        $normalizado = trim(str_replace(' de ', ' ', ' ' . mb_strtolower($fecha) . ' '));
        $normalizado = strtr($normalizado, [
            'enero' => 'january', 'febrero' => 'february', 'marzo' => 'march', 'abril' => 'april',
            'mayo' => 'may', 'junio' => 'june', 'julio' => 'july', 'agosto' => 'august',
            'septiembre' => 'september', 'setiembre' => 'september', 'octubre' => 'october',
            'noviembre' => 'november', 'diciembre' => 'december',
        ]);

        try {
            return Carbon::parse($normalizado)->startOfDay();
        } catch (\Throwable $e) {
            return null;
        }
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
    /**
     * ¿El primer candidato es un ganador claro (se puede resolver solo, sin
     * preguntar)? Solo cuando es el único candidato, o cuando tiene
     * confianza alta Y no hay otro candidato empatado en el mismo score —
     * un empate significa que en realidad SÍ es ambiguo (ej. dos clientes
     * "Juan Pérez" distintos) y no se debe elegir uno al azar.
     *
     * @param array<int, array{id: int, nombre: string, score: float}> $matches
     */
    private function isClearWinner(array $matches): bool
    {
        if (count($matches) === 1) {
            return true;
        }

        return $matches[0]['score'] >= self::UMBRAL_CONFIANZA
            && $matches[0]['score'] > $matches[1]['score'];
    }

    private function bestMatches(string $query, array $candidates, int $limit = 3): array
    {
        $q      = $this->normalize($query);
        $qWords = array_values(array_filter(explode(' ', $q), fn ($w) => $w !== ''));
        $scored = [];

        foreach ($candidates as $id => $nombre) {
            $n = $this->normalize((string) $nombre);

            // Puntúa por PALABRAS, no por la cadena completa letra por
            // letra: un nombre con más palabras que la búsqueda (ej.
            // "CRISTINA GUADALUPE PECH SOLÍS" contra "cristina pech") no
            // debe quedar en desventaja frente a un candidato más corto
            // que por pura coincidencia de letras saca mejor puntaje con
            // similar_text() (ej. "MIRNA PECH"). Si cada palabra de la
            // búsqueda aparece en el nombre del candidato, es un match
            // fuerte sin importar cuántas palabras más tenga el candidato.
            $score = $this->wordOverlapScore($qWords, array_values(array_filter(explode(' ', $n), fn ($w) => $w !== '')));

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

    /**
     * Qué proporción de las palabras de la búsqueda aparecen en el
     * candidato (exactas, o muy parecidas para tolerar typos por letra).
     * Palabras de más en el candidato (segundo nombre, apellido materno,
     * etc.) no penalizan — solo importa que las palabras que SÍ escribió
     * el usuario estén ahí.
     */
    private function wordOverlapScore(array $queryWords, array $candidateWords): float
    {
        if (empty($queryWords)) {
            return 0.0;
        }

        $matched = 0;
        foreach ($queryWords as $qw) {
            $best = 0.0;
            foreach ($candidateWords as $cw) {
                if ($qw === $cw) {
                    $best = 1.0;
                    break;
                }
                similar_text($qw, $cw, $pct);
                $best = max($best, $pct / 100);
            }
            if ($best >= 0.75) {
                $matched++;
            }
        }

        return $matched / count($queryWords);
    }
}
