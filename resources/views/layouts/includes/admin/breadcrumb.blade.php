@props(['breadcrumbs' => []])

@if (!empty($breadcrumbs) && count($breadcrumbs))
<nav class="mb-0" aria-label="Breadcrumb">
    <ol class="flex flex-wrap items-center text-sm text-slate-700 dark:text-slate-300">
        @foreach ($breadcrumbs as $i => $bc)
            @php
                $text = $bc['name'] ?? $bc['label'] ?? '';
                $href = $bc['url']  ?? $bc['href'] ?? null;
            @endphp

            <li class="flex items-center {{ $i ? "pl-2 before:content-['/'] before:pr-2 before:text-slate-400" : '' }}">
                @if ($href)
                    <a href="{{ $href }}" class="opacity-60 hover:opacity-100">{{ $text }}</a>
                @else
                    <span class="font-medium">{{ $text }}</span>
                @endif
            </li>
        @endforeach
    </ol>
</nav>
@endif
