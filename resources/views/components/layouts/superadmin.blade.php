@props(['title' => '', 'breadcrumbs' => []])

<x-layouts.superadmin :title="$title" :breadcrumbs="$breadcrumbs">
    {{ $slot }}
</x-layouts.superadmin>