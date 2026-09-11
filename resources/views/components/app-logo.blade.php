@props([
    'sidebar' => false,
])

@php
    $brandingSchoolId = \Modules\Core\Domain\Support\SchoolContext::currentId() ?? auth()->user()?->primarySchool()?->id;
    $brandingSchool = $brandingSchoolId !== null ? \Modules\Core\Models\School::find($brandingSchoolId) : null;
@endphp

<a {{ $attributes->except('sidebar')->class($sidebar ? 'app-sidebar-brand' : 'app-brand-link') }}>
    @if ($brandingSchool?->logo_path)
        <img
            src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($brandingSchool->logo_path) }}"
            alt="{{ $brandingSchool->name }}"
            style="{{ $sidebar ? 'height: auto;width:auto;max-width: 190px;' : 'height:20px; width:auto; max-width:38px;' }}"
        >
    @else
        <x-app-logo-icon :width="$sidebar ? '32' : '38'" :height="$sidebar ? '17' : '20'" />
    @endif
</a>
