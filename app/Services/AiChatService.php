<?php

namespace App\Services;

use App\Models\AssistantConversation;
use App\Models\SalesOrder;
use App\Models\SystemSetting;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Orquesta la conversación del chat de asistencia con un proveedor de IA
 * compatible con la API "chat completions" de OpenAI (tool/function calling).
 * Sigue el mismo patrón que WhatsappSender: config desde SystemSetting con
 * fallback a .env, isConfigured() antes de llamar, respuesta uniforme.
 */
class AiChatService
{
    private string $apiKey;
    private string $model;
    private string $baseUrl;

    private const MAX_TURNOS = 6;

    public function __construct(private OrderAssistantService $orders)
    {
        $this->apiKey  = $this->firstNonEmpty(SystemSetting::get('openai.api_key'), env('OPENAI_API_KEY', ''));
        $this->model   = $this->firstNonEmpty(SystemSetting::get('openai.model'), env('OPENAI_MODEL', 'gpt-4o-mini'));
        $this->baseUrl = rtrim($this->firstNonEmpty(SystemSetting::get('openai.base_url'), env('OPENAI_BASE_URL', 'https://api.openai.com/v1')), '/');
    }

    private function firstNonEmpty(?string $primary, string $fallback): string
    {
        return filled($primary) ? $primary : $fallback;
    }

    public function isConfigured(): bool
    {
        return $this->apiKey !== '';
    }

    /**
     * Prueba rápida de conexión (sin herramientas) para el botón "Probar
     * conexión" en Superadmin → Configuración — no toca ningún pedido.
     */
    public function testConnection(): array
    {
        if (! $this->isConfigured()) {
            return ['ok' => false, 'message' => 'Faltan datos por configurar (API Key).'];
        }

        $resp = $this->chatSinHerramientas([
            ['role' => 'system', 'content' => 'Responde únicamente con la palabra: listo'],
            ['role' => 'user', 'content' => 'ping'],
        ]);

        if (! ($resp['ok'] ?? false)) {
            $body = $resp['body'] ?? '';
            $detalle = is_array($body) ? ($body['error']['message'] ?? json_encode($body)) : $body;
            return ['ok' => false, 'message' => 'El proveedor respondió ' . ($resp['status'] ?? '?') . ': ' . $detalle];
        }

        $reply = $resp['body']['choices'][0]['message']['content'] ?? '';
        return ['ok' => true, 'message' => 'Conexión exitosa con el modelo ' . $this->model . '. Respuesta: "' . trim($reply) . '".'];
    }

    private function chatSinHerramientas(array $messages): array
    {
        $req = Http::withToken($this->apiKey)
            ->timeout(20)
            ->post("{$this->baseUrl}/chat/completions", [
                'model'    => $this->model,
                'messages' => $messages,
            ]);

        return [
            'ok'     => $req->successful(),
            'status' => $req->status(),
            'body'   => $req->json() ?: $req->body(),
        ];
    }

    /**
     * Procesa un mensaje del usuario dentro de una conversación: llama al
     * modelo, ejecuta las herramientas que pida (buscar cliente/producto,
     * crear/confirmar/cancelar pedido) y regresa la respuesta final.
     *
     * @return array{ok: bool, reply: string, draft_order: ?SalesOrder}
     */
    public function handleMessage(AssistantConversation $conversation, string $userMessage, User $user): array
    {
        // El mensaje del usuario se guarda SIEMPRE, incluso si la IA no está
        // configurada o falla después — si no, el historial que ve el widget
        // al recargar la página (vía historial()) sale vacío y parece que la
        // conversación nunca existió.
        $conversation->messages()->create(['role' => 'user', 'content' => $userMessage]);

        if (! $this->isConfigured()) {
            $reply = 'El asistente de IA no está configurado. Pide a un administrador que defina OPENAI_API_KEY.';
            $conversation->messages()->create(['role' => 'assistant', 'content' => $reply]);
            return ['ok' => false, 'reply' => $reply, 'draft_order' => null];
        }

        $history    = $this->buildHistory($conversation);
        $draftOrder = null;

        for ($turno = 0; $turno < self::MAX_TURNOS; $turno++) {
            $resp = $this->chat($history);

            if (! ($resp['ok'] ?? false)) {
                Log::warning('AiChatService: fallo llamando al proveedor de IA', ['body' => $resp['body'] ?? null]);
                $reply = 'Hubo un problema hablando con el asistente de IA. Intenta de nuevo.';
                $conversation->messages()->create(['role' => 'assistant', 'content' => $reply]);
                return ['ok' => false, 'reply' => $reply, 'draft_order' => $draftOrder];
            }

            $message = $resp['body']['choices'][0]['message'] ?? null;
            if (! $message) {
                return ['ok' => false, 'reply' => 'Respuesta inesperada del asistente de IA.', 'draft_order' => $draftOrder];
            }

            $history[] = $message;
            $toolCalls = $message['tool_calls'] ?? [];

            if (empty($toolCalls)) {
                $reply = trim((string) ($message['content'] ?? ''));
                $conversation->messages()->create(['role' => 'assistant', 'content' => $reply]);
                return ['ok' => true, 'reply' => $reply, 'draft_order' => $draftOrder];
            }

            foreach ($toolCalls as $call) {
                $name = $call['function']['name'] ?? '';
                $args = json_decode($call['function']['arguments'] ?? '{}', true) ?: [];

                $result = $this->runTool($name, $args, $conversation, $user);

                if ($name === 'crear_borrador_pedido' && ($result['ok'] ?? false) && ($result['order'] ?? null)) {
                    $draftOrder = $result['order'];
                }

                $history[] = [
                    'role'         => 'tool',
                    'tool_call_id' => $call['id'] ?? '',
                    'content'      => json_encode($this->toolResultForModel($result), JSON_UNESCAPED_UNICODE),
                ];
            }
        }

        $conversation->messages()->create(['role' => 'assistant', 'content' => 'No pude terminar de procesar el pedido, ¿puedes repetirlo de forma más simple?']);
        return ['ok' => false, 'reply' => 'No pude terminar de procesar el pedido, ¿puedes repetirlo de forma más simple?', 'draft_order' => $draftOrder];
    }

    private function chat(array $messages): array
    {
        $req = Http::withToken($this->apiKey)
            ->timeout(30)
            ->post("{$this->baseUrl}/chat/completions", [
                'model'       => $this->model,
                'messages'    => $messages,
                'tools'       => $this->toolDefinitions(),
                'tool_choice' => 'auto',
            ]);

        return [
            'ok'     => $req->successful(),
            'status' => $req->status(),
            'body'   => $req->json() ?: $req->body(),
        ];
    }

    private function buildHistory(AssistantConversation $conversation): array
    {
        $history = [
            ['role' => 'system', 'content' => $this->systemPrompt()],
        ];

        foreach ($conversation->messages()->orderBy('id')->get() as $m) {
            $history[] = ['role' => $m->role, 'content' => (string) $m->content];
        }

        return $history;
    }

    private function systemPrompt(): string
    {
        $hoy = now()->translatedFormat('l j \d\e F \d\e Y'); // ej. "jueves 27 de agosto de 2026"

        return <<<PROMPT
Eres el asistente de captura de pedidos de un sistema de carnicería/distribución. Los agentes te dictan pedidos en lenguaje natural, por ejemplo: "Angel Galera, 10 kilos de panza, 10 de pata".

Hoy es {$hoy}. Usa esta fecha como referencia para interpretar cualquier fecha relativa o incompleta que mencione el usuario.

Reglas:
1. Antes de crear cualquier pedido, usa buscar_cliente para resolver el nombre del cliente y buscar_producto para cada producto mencionado. Nunca inventes un client_id o product_id sin haberlo resuelto con estas herramientas.
2. Si buscar_cliente o buscar_producto regresan varios candidatos (status ambiguous), pregunta al usuario cuál es el correcto antes de continuar. Si no hay ningún candidato (status not_found), dile que no lo encontraste y pide que lo aclare o lo escriba distinto.
3. Si el usuario menciona una fecha para el pedido, en cualquier formato ("28-08-2026", "28/08/2026", "28 de agosto", "28/08", "mañana", "el viernes"), interprétala usando la fecha de hoy como referencia y mándala en el campo "programado_para" de crear_borrador_pedido en formato YYYY-MM-DD. Si no menciona ninguna fecha, omite ese campo (el sistema usa mañana por default).
4. Cuando ya tengas el cliente (o el usuario confirme que no hay cliente registrado) y todos los productos resueltos con su cantidad, usa crear_borrador_pedido.
5. Después de crear el borrador, muestra un resumen claro (cliente, cada producto con cantidad y precio, fecha programada, total) y pregunta si lo confirma.
6. Si el usuario confirma (sí, confirmo, dale, etc.), usa confirmar_pedido con el order_id del borrador ya creado en esta conversación.
7. Si el usuario rechaza o pide cancelar, usa cancelar_pedido con ese order_id.
8. Una vez que ya confirmaste un pedido (usaste confirmar_pedido) o lo cancelaste, NUNCA vuelvas a llamar crear_borrador_pedido para esos mismos productos — ese pedido ya quedó resuelto. Si el usuario responde algo genérico después ("ok", "gracias", "va", etc.) sin mencionar un pedido nuevo, solo confírmale que ya quedó listo, no repitas ninguna herramienta.
9. Solo llama crear_borrador_pedido de nuevo dentro de la misma conversación si el usuario claramente está pidiendo un pedido DISTINTO (otro cliente, u otros productos/cantidades que no sean los del pedido que ya se creó), O si solo está corrigiendo la fecha de ese mismo pedido (en ese caso manda los mismos productos con la fecha nueva — el sistema detecta que es el mismo pedido y solo actualiza la fecha, no lo duplica).
10. Si el usuario escribe algo entre paréntesis junto a un producto (ej. "10 kg milanesa de cerdo (descongelada)" o "5 de pata (para caldo)"), eso NO es parte del nombre del producto — es una nota de esa línea. Usa buscar_producto solo con el nombre limpio (sin el paréntesis), y al llamar crear_borrador_pedido manda ese texto (sin los paréntesis) en el campo "comentario" de esa línea, para que quede junto a la descripción del pedido.
11. Responde siempre en español, de forma breve y clara. No inventes datos que no vengan de las herramientas.
PROMPT;
    }

    private function toolDefinitions(): array
    {
        return [
            [
                'type'     => 'function',
                'function' => [
                    'name'        => 'buscar_cliente',
                    'description' => 'Busca un cliente registrado en el sistema por nombre o apodo.',
                    'parameters'  => [
                        'type'       => 'object',
                        'properties' => ['nombre' => ['type' => 'string']],
                        'required'   => ['nombre'],
                    ],
                ],
            ],
            [
                'type'     => 'function',
                'function' => [
                    'name'        => 'buscar_producto',
                    'description' => 'Busca un producto del catálogo por nombre o corte coloquial.',
                    'parameters'  => [
                        'type'       => 'object',
                        'properties' => ['nombre' => ['type' => 'string']],
                        'required'   => ['nombre'],
                    ],
                ],
            ],
            [
                'type'     => 'function',
                'function' => [
                    'name'        => 'crear_borrador_pedido',
                    'description' => 'Crea un pedido en borrador con el cliente y productos YA resueltos por id real (obtenidos con buscar_cliente/buscar_producto).',
                    'parameters'  => [
                        'type'       => 'object',
                        'properties' => [
                            'client_id'       => ['type' => ['integer', 'null'], 'description' => 'id real del cliente resuelto, o null si no hay cliente registrado'],
                            'programado_para' => ['type' => ['string', 'null'], 'description' => 'Fecha para la que el usuario pidió el pedido, en formato YYYY-MM-DD, solo si mencionó una fecha. Omite o null si no mencionó ninguna (el sistema usa mañana por default).'],
                            'items'           => [
                                'type'  => 'array',
                                'items' => [
                                    'type'       => 'object',
                                    'properties' => [
                                        'product_id' => ['type' => 'integer'],
                                        'cantidad'   => ['type' => 'number'],
                                        'comentario' => ['type' => ['string', 'null'], 'description' => 'Nota de esa línea (ej. "descongelada", "en trozos") — normalmente lo que el usuario escribió entre paréntesis junto al producto. Sin paréntesis en el valor.'],
                                    ],
                                    'required' => ['product_id', 'cantidad'],
                                ],
                            ],
                        ],
                        'required' => ['items'],
                    ],
                ],
            ],
            [
                'type'     => 'function',
                'function' => [
                    'name'        => 'confirmar_pedido',
                    'description' => 'Confirma un pedido en borrador ya creado en esta conversación, cuando el usuario dice que sí.',
                    'parameters'  => [
                        'type'       => 'object',
                        'properties' => ['order_id' => ['type' => 'integer']],
                        'required'   => ['order_id'],
                    ],
                ],
            ],
            [
                'type'     => 'function',
                'function' => [
                    'name'        => 'cancelar_pedido',
                    'description' => 'Cancela/descarta un pedido en borrador ya creado en esta conversación, cuando el usuario dice que no.',
                    'parameters'  => [
                        'type'       => 'object',
                        'properties' => ['order_id' => ['type' => 'integer']],
                        'required'   => ['order_id'],
                    ],
                ],
            ],
        ];
    }

    private function runTool(string $name, array $args, AssistantConversation $conversation, User $user): array
    {
        return match ($name) {
            'buscar_cliente'        => $this->orders->resolveClient((string) ($args['nombre'] ?? '')),
            'buscar_producto'       => $this->orders->resolveProduct((string) ($args['nombre'] ?? '')),
            'crear_borrador_pedido' => $this->orders->createDraft(
                ['client_id' => $args['client_id'] ?? null],
                $args['items'] ?? [],
                $user,
                $conversation->id,
                $args['programado_para'] ?? null
            ),
            'confirmar_pedido' => $this->findAndRun($args, fn ($order) => $this->orders->confirm($order, $user)),
            'cancelar_pedido'  => $this->findAndRun($args, fn ($order) => $this->orders->cancel($order, $user)),
            default            => ['ok' => false, 'message' => "Herramienta desconocida: {$name}."],
        };
    }

    private function findAndRun(array $args, \Closure $action): array
    {
        $order = SalesOrder::find($args['order_id'] ?? null);
        if (! $order) {
            return ['ok' => false, 'message' => 'No encontré ese pedido.'];
        }
        return $action($order);
    }

    /**
     * Aplana el resultado (puede traer un modelo SalesOrder) a algo simple
     * que el modelo de IA pueda leer directamente en el mensaje "tool".
     */
    private function toolResultForModel(array $result): array
    {
        if (isset($result['order']) && $result['order'] instanceof SalesOrder) {
            $order = $result['order'];
            $result['order'] = [
                'id'              => $order->id,
                'folio'           => $order->folio,
                'cliente'         => $order->client?->nombre ?? 'Sin cliente registrado',
                'programado_para' => optional($order->programado_para)->format('Y-m-d'),
                'items'           => $order->items->map(fn ($i) => [
                    'producto' => $i->descripcion,
                    'cantidad' => (float) $i->cantidad,
                    'precio'   => (float) $i->precio,
                    'total'    => (float) $i->total,
                ])->all(),
                'total' => (float) $order->total,
            ];
        }

        return $result;
    }
}
