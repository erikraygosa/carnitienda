    @php
    $pasos = [
        1 => ['label' => 'Datos generales',   'icon' => '🏢'],
        2 => ['label' => 'Datos fiscales',     'icon' => '📋'],
        3 => ['label' => 'Certificados',       'icon' => '🔐'],
    ];
@endphp

<div class="flex items-center gap-0">
    @foreach ($pasos as $num => $info)

        {{-- Paso --}}
        <div class="flex items-center gap-2
            @if($num < $paso) text-green-600
            @elseif($num === $paso) text-indigo-600
            @else text-gray-400
            @endif">

            <div class="flex items-center justify-center w-8 h-8 rounded-full text-sm font-semibold border-2
                @if($num < $paso) bg-green-100 border-green-500
                @elseif($num === $paso) bg-indigo-100 border-indigo-500
                @else bg-gray-100 border-gray-300
                @endif">
                @if($num < $paso)
                    ✓
                @else
                    {{ $num }}
                @endif
            </div>

            <span class="text-sm font-medium hidden sm:block">{{ $info['label'] }}</span>
        </div>

        {{-- Línea separadora --}}
        @if(!$loop->last)
            <div class="flex-1 h-0.5 mx-3
                @if($num < $paso) bg-green-400
                @else bg-gray-200
                @endif">
            </div>
        @endif

    @endforeach
</div>