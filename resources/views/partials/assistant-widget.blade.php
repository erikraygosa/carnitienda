{{-- Chat de asistencia flotante — disponible para cualquier usuario autenticado.
     Crear/confirmar/cancelar un pedido real sigue exigiendo el permiso
     'crear pedidos' del lado del servidor, aunque el widget se vea igual
     para todos. --}}
<div id="assistant-widget" class="fixed bottom-4 right-4 z-50">

    <button id="assistant-toggle" type="button"
        class="relative w-14 h-14 rounded-full bg-indigo-600 hover:bg-indigo-700 text-white shadow-lg flex items-center justify-center text-2xl">
        <i class="fa-solid fa-comment-dots"></i>
        {{-- Punto que indica que hay una conversación en curso minimizada, para saber que hay algo que retomar. --}}
        <span id="assistant-active-dot" class="hidden absolute top-0 right-0 w-3.5 h-3.5 bg-emerald-400 border-2 border-white rounded-full"></span>
    </button>

    <div id="assistant-panel"
        class="hidden flex-col bg-white border border-gray-200 rounded-xl shadow-2xl w-96 max-w-[92vw] h-[32rem] max-h-[80vh] absolute bottom-16 right-0 overflow-hidden">

        <div class="bg-indigo-600 text-white px-4 py-3 flex items-center justify-between">
            <div class="font-semibold text-sm">Asistente de pedidos</div>
            <div class="flex items-center gap-3">
                <button id="assistant-minimize" type="button" class="text-white/80 hover:text-white" title="Minimizar (sigue la conversación)">
                    <i class="fa-solid fa-minus"></i>
                </button>
                <button id="assistant-close" type="button" class="text-white/80 hover:text-white" title="Cerrar conversación">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </div>
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
    var minimizeBtn = document.getElementById('assistant-minimize');
    var closeBtn    = document.getElementById('assistant-close');
    var activeDot   = document.getElementById('assistant-active-dot');
    var panel       = document.getElementById('assistant-panel');
    var form        = document.getElementById('assistant-form');
    var input       = document.getElementById('assistant-input');
    var messagesBox = document.getElementById('assistant-messages');
    var csrfToken   = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
    var conversationId = null;
    var placeholderHtml = messagesBox.innerHTML;

    // Esto NO es un SPA: cada navegación (ej. Dashboard → Pedidos) recarga
    // la página completa y el JS pierde todo lo que tenía en memoria. Para
    // que la conversación sobreviva a esos saltos de página, se guarda el
    // conversation_id (y si el panel estaba visible) en sessionStorage — solo
    // dura mientras la pestaña siga abierta, y al cargar cada página se
    // rehidrata el historial real desde el servidor (fuente de verdad).
    var STORAGE_KEY_CONV = 'assistant_conversation_id';
    var STORAGE_KEY_OPEN = 'assistant_widget_open';

    function persistState(open) {
        try {
            if (conversationId) {
                sessionStorage.setItem(STORAGE_KEY_CONV, conversationId);
                sessionStorage.setItem(STORAGE_KEY_OPEN, open ? '1' : '0');
            } else {
                sessionStorage.removeItem(STORAGE_KEY_CONV);
                sessionStorage.removeItem(STORAGE_KEY_OPEN);
            }
        } catch (e) { /* modo privado o storage bloqueado — sin persistencia, no rompe nada */ }
    }

    function togglePanel(show) {
        panel.classList.toggle('hidden', !show);
        panel.classList.toggle('flex', show);
        if (show) {
            input.focus();
            activeDot.classList.add('hidden');
        } else if (conversationId) {
            // Se minimizó (o se cerró la ventana sin terminar) con una
            // conversación en curso — el punto avisa que hay algo que retomar.
            activeDot.classList.remove('hidden');
        }
        persistState(show);
    }

    // Minimizar: solo oculta el panel, la conversación sigue intacta — al
    // reabrir (con el botón flotante, en esta página o en otra) se retoma
    // tal cual se dejó, para poder ver el pedido de fondo y seguir
    // trabajando sin perder el chat.
    toggleBtn.addEventListener('click', function () { togglePanel(panel.classList.contains('hidden')); });
    minimizeBtn.addEventListener('click', function () { togglePanel(false); });

    // Cerrar: termina la conversación de verdad — limpia el historial, el
    // conversation_id y lo guardado en sessionStorage, para que la próxima
    // vez (aquí o en otra página) se empiece desde cero.
    closeBtn.addEventListener('click', function () {
        togglePanel(false);
        conversationId = null;
        messagesBox.innerHTML = placeholderHtml;
        activeDot.classList.add('hidden');
        persistState(false);
    });

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
            (order.programado_para ? '<div class="text-xs text-gray-500 mb-1">Programado para: ' + order.programado_para + '</div>' : '') +
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
            persistState(!panel.classList.contains('hidden'));
            if (data.reply) addBubble(data.reply, 'assistant');
            if (data.draft_order) addDraftCard(data.draft_order);
        })
        .catch(function () {
            thinking.remove();
            addBubble('No se pudo conectar con el asistente.', 'assistant');
        });
    });

    // Al cargar cualquier página del admin: si había una conversación en
    // curso (guardada en sessionStorage desde otra página o de antes de
    // recargar), se trae su historial real del servidor y se reconstruye
    // el chat tal cual estaba — el panel vuelve a abrirse solo si se había
    // dejado abierto (no minimizado) antes de navegar.
    (function restoreConversation() {
        var savedId, savedOpen;
        try {
            savedId   = sessionStorage.getItem(STORAGE_KEY_CONV);
            savedOpen = sessionStorage.getItem(STORAGE_KEY_OPEN) === '1';
        } catch (e) { return; }

        if (!savedId) return;

        fetch('{{ url("admin/asistente/conversaciones") }}/' + savedId, {
            headers: { 'Accept': 'application/json' }
        })
        .then(function (r) { if (!r.ok) throw new Error('no encontrada'); return r.json(); })
        .then(function (data) {
            conversationId = data.conversation_id;
            messagesBox.innerHTML = '';
            (data.messages || []).forEach(function (m) {
                addBubble(m.content, m.role === 'user' ? 'user' : 'assistant');
            });
            if (data.draft_order) addDraftCard(data.draft_order);
            togglePanel(savedOpen);
        })
        .catch(function () {
            // La conversación ya no existe o no es de este usuario — se
            // limpia lo guardado para no reintentarlo en cada página.
            try {
                sessionStorage.removeItem(STORAGE_KEY_CONV);
                sessionStorage.removeItem(STORAGE_KEY_OPEN);
            } catch (e) {}
        });
    })();
})();
</script>
