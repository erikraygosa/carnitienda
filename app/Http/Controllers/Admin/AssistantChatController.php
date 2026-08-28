<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AssistantConversation;
use App\Models\SalesOrder;
use App\Services\AiChatService;
use App\Services\OrderAssistantService;
use Illuminate\Http\Request;

class AssistantChatController extends Controller
{
    public function __construct(
        private AiChatService $ai,
        private OrderAssistantService $orders,
    ) {}

    /**
     * Widget disponible para cualquier usuario autenticado (ruta solo con
     * 'auth', sin permiso Spatie) — pero crear/confirmar/cancelar un pedido
     * real sigue exigiendo el permiso 'crear pedidos' (validado dentro de
     * OrderAssistantService), igual que el formulario normal.
     */
    public function mensaje(Request $request)
    {
        $data = $request->validate([
            'conversation_id' => ['nullable', 'integer', 'exists:assistant_conversations,id'],
            'mensaje'         => ['required', 'string', 'max:2000'],
        ]);

        $conversation = $data['conversation_id']
            ? AssistantConversation::where('user_id', $request->user()->id)->findOrFail($data['conversation_id'])
            : AssistantConversation::create(['user_id' => $request->user()->id]);

        $result = $this->ai->handleMessage($conversation, $data['mensaje'], $request->user());

        return response()->json([
            'conversation_id' => $conversation->id,
            'reply'           => $result['reply'] ?? '',
            'draft_order'     => $this->summarize($result['draft_order'] ?? null),
        ]);
    }

    /**
     * Devuelve el historial de una conversación + el borrador pendiente (si
     * lo hay) — usado por el widget para recuperar la conversación cuando
     * el usuario navega a otra página (esto no es un SPA: cada navegación
     * recarga todo y el JS pierde el estado en memoria; el navegador solo
     * guarda el conversation_id en sessionStorage y lo rehidrata desde aquí).
     */
    public function historial(Request $request, AssistantConversation $conversation)
    {
        if ($conversation->user_id !== $request->user()->id) {
            abort(403);
        }

        $pendiente = SalesOrder::where('assistant_conversation_id', $conversation->id)
            ->where('status', SalesOrder::S_BORRADOR)
            ->orderByDesc('id')
            ->first();

        return response()->json([
            'conversation_id' => $conversation->id,
            'messages'        => $conversation->messages()->orderBy('id')->get(['role', 'content'])->map(fn ($m) => [
                'role'    => $m->role,
                'content' => $m->content,
            ]),
            'draft_order' => $this->summarize($pendiente),
        ]);
    }

    public function confirmar(Request $request, SalesOrder $order)
    {
        $result = $this->orders->confirm($order, $request->user());
        return response()->json($result + ['draft_order' => $this->summarize($order)]);
    }

    public function cancelar(Request $request, SalesOrder $order)
    {
        $result = $this->orders->cancel($order, $request->user());
        return response()->json($result);
    }

    private function summarize(?SalesOrder $order): ?array
    {
        if (! $order) {
            return null;
        }

        $order->loadMissing('items', 'client');

        return [
            'id'              => $order->id,
            'folio'           => $order->folio,
            'status'          => $order->status,
            'cliente'         => $order->client?->nombre ?? 'Sin cliente',
            'programado_para' => optional($order->programado_para)->format('d/m/Y'),
            'items'           => $order->items->map(fn ($i) => [
                'producto' => $i->descripcion,
                'cantidad' => (float) $i->cantidad,
                'precio'   => (float) $i->precio,
                'total'    => (float) $i->total,
            ])->all(),
            'total' => (float) $order->total,
        ];
    }
}
