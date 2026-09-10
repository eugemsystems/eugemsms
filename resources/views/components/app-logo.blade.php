@props([
    'sidebar' => false,
])

<a {{ $attributes->except('sidebar')->class($sidebar ? 'app-sidebar-brand' : 'app-brand-link') }}>
    <x-app-logo-icon :width="$sidebar ? '32' : '38'" :height="$sidebar ? '17' : '20'" />
</a>
