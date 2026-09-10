@props(['illustration' => 'login', 'title' => null])

<x-layouts::auth.simple :title="$title ?? null" :illustration="$illustration">
    {{ $slot }}
</x-layouts::auth.simple>
