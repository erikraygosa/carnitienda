{{-- Chat de asistencia flotante — disponible para cualquier usuario autenticado.
     Crear/confirmar/cancelar un pedido real sigue exigiendo el permiso
     'crear pedidos' del lado del servidor, aunque el widget se vea igual
     para todos. --}}
<div id="assistant-widget" class="fixed bottom-4 right-4 z-50">

    <button id="assistant-toggle" type="button"
        class="w-14 h-14 rounded-full bg-indigo-600 hover:bg-indigo-700 text-white shadow-lg flex items-center justify-center text-2xl">
        <i class="fa-solid fa-comment-dots"></i>
    </button>

    <div id="assistant-panel"
        class="hidden flex-col bg-white border border-gray-200 rounded-xl shadow-2xl w-96 max-w-[92vw] h-[32rem] max-h-[80vh] absolute bottom-16 right-0 overflow-hidden">

        <div class="bg-indigo-600 text-white px-4 py-3 flex items-center justify-between">
            <div class="font-semibold text-sm">Asistente de pedidos</div>
            <button id="assistant-close" type="button" class="text-white/80 hover:text-white">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>

        <div id="assistant-messages" class="flex-1 overflow-y-auto px-3 py-3 space-y-3 text-sm bg-gray-50">
            <div class="text-gray-500 text-xs text-center">
                Dicta el pedido tal como lo escribirías en WhatsApp, ej:<br>
                <span class="italic">"Angel Galera, 10 kilos de panza, 10 de pata"</span>
            </div>
        </div>

        <form id="assistant-form" class="border-t border-gray-200 p-2 flex gap-2">
            <input id="assistant-input" type="text" autocomplete="off" placeholder="Escribe el pedido…"
                class="flex-1 rounded-lg border-gray-300 text-sm focus:ring-indigo-500 focus:border-indigo-500">
            <button type="submit"
                class="w-10 h-10 rounded-lg bg-indigo-600 hover:bg-indigo-700 text-white flex items-center justify-center">
                <i class="fa-solid fa-paper-plane"></i>
            </button>
        </form>
    </div>
</div>

<script>
(function () {
    var toggleBtn   = document.getElementById('assistant-toggle');
    var closeBtn    = document.getElementById('assistant-close');
    var panel       = document.getElementById('assistant-panel');
    var form        = document.getElementById('assistant-form');
    var input       = document.getElementById('assistant-input');
    var messagesBox = document.getElementById('assistant-messages');
    var csrfToken   = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
    var conversationId = null;

    function togglePanel(show) {
        panel.classList.toggle('hidden', !show);
        panel.classList.toggle('flex', show);
        if (show) input.focus();
    }

    toggleBtn.addEventListener('click', function () { togglePanel(panel.classList.contains('hidden')); });
    closeBtn.addEventListener('click', function () { togglePanel(false); });

    function addBubble(text, who) {
        var wrap = document.createElement('div');
        wrap.className = who === 'user'
            ? 'ml-auto bg-indigo-600 text-white rounded-lg rounded-br-none px-3 py-2 max-w-[85%] whitespace-pre-wrap'
            : 'mr-auto bg-white border border-gray-200 rounded-lg rounded-bl-none px-3 py-2 max-w-[85%] whitespace-pre-wrap';
        wrap.textContent = text;
        messagesBox.appendChild(wrap);
        messagesBox.scrollTop = messagesBox.scrollHeight;
        return wrap;
    }

    function addDraftCard(order) {
        var card = document.createElement('div');
        card.className = 'mr-auto bg-white border border-indigo-200 rounded-lg px-3 py-2 max-w-[92%] w-full shadow-sm';

        var itemsHtml = order.items.map(function (it) {
            return '<div class="flex justify-between text-xs text-gray-600">' +
                '<span>' + it.cantidad + ' × ' + it.producto + '</span>' +
                '<span>$' + it.total.toFixed(2) + '</span></div>';
        }).join('');

        card.innerHTML =
            '<div class="text-xs font-semibold text-gray-700 mb-1">Borrador ' + order.folio + ' — ' + order.cliente + '</div>' +
            itemsHtml +
            '<div class="flex justify-between font-semibold text-sm mt-1 border-t pt-1">' +
                '<span>Total</span><span>$' + order.total.toFixed(2) + '</span></div>' +
            '<div class="flex gap-2 mt-2">' +
                '<button data-action="confirmar" class="flex-1 bg-emerald-600 hover:bg-emerald-700 text-white text-xs rounded-md py-1.5">Confirmar</button>' +
                '<button data-action="cancelar" class="flex-1 bg-rose-100 hover:bg-rose-200 text-rose-700 text-xs rounded-md py-1.5">Cancelar</button>' +
            '</div>';

        card.querySelector('[data-action="confirmar"]').addEventListener('click', function () {
            orderAction(order.id, 'confirmar', card);
        });
        card.querySelector('[data-action="cancelar"]').addEventListener('click', function () {
            orderAction(order.id, 'cancelar', card);
        });

        messagesBox.appendChild(card);
        messagesBox.scrollTop = messagesBox.scrollHeight;
    }

    function orderAction(orderId, action, card) {
        fetch('{{ url("admin/asistente/pedidos") }}/' + orderId + '/' + action, {
            method: 'POST',
            headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': csrfToken }
        })
        .then(function (r) { return r.json(); })
        .then(function (data) {
            card.remove();
            addBubble(data.message || (data.ok ? 'Listo.' : 'No se pudo completar la acción.'), 'assistant');
        })
        .catch(function () {
            addBubble('No se pudo conectar con el servidor.', 'assistant');
        });
    }

    form.addEventListener('submit', function (e) {
        e.preventDefault();
        var text = input.value.trim();
        if (!text) return;

        addBubble(text, 'user');
        input.value = '';
        var thinking = addBubble('Escribiendo…', 'assistant');

        fetch('{{ route('admin.asistente.mensaje') }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': csrfToken
            },
            body: JSON.stringify({ conversation_id: conversationId, mensaje: text })
        })
        .then(function (r) { return r.json(); })
        .then(function (data) {
            thinking.remove();
            conversationId = data.conversation_id || conversationId;
            if (data.reply) addBubble(data.reply, 'assistant');
            if (data.draft_order) addDraftCard(data.draft_order);
        })
        .catch(function () {
            thinking.remove();
            addBubble('No se pudo conectar con el asistente.', 'assistant');
        });
    });
})();
</script>
