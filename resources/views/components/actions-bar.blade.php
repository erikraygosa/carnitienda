<div {{ $attributes->merge(['class'=>'flex items-center space-x-2']) }}>
    {{-- Editar siempre --}}
    <x-wire-button :href="$editUrl" blue xs>Editar</x-wire-button>

    {{-- PDF + Envío: ponlos siempre o condicional por estado desde el padre --}}
    @isset($pdfUrl)
        <x-wire-button :href="$pdfUrl" gray outline xs target="_blank">Ver PDF</x-wire-button>
    @endisset
    @isset($pdfDlUrl)
        <x-wire-button :href="$pdfDlUrl" gray xs>Descargar PDF</x-wire-button>
    @endisset
    @isset($sendUrl)
        <x-wire-button :href="$sendUrl" violet xs>Enviar</x-wire-button>
    @endisset

    {{-- Acciones de estado (el padre decide cuáles renderizar) --}}
    {{ $slot }}
</div>
