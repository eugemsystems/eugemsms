@php
    $tabs = [
        'profile' => ['label' => __('Profile'), 'route' => 'schools.profile'],
        'branding' => ['label' => __('Branding'), 'route' => 'schools.branding'],
        'structure' => ['label' => __('Academic structure'), 'route' => 'structure.index'],
        'houses' => ['label' => __('Houses'), 'route' => 'houses.index'],
        'modules' => ['label' => __('Modules'), 'route' => 'modules.index'],
        'users' => ['label' => __('Users'), 'route' => 'schools.users'],
        'clone' => ['label' => __('Clone setup'), 'route' => 'schools.clone'],
    ];
@endphp

<div class="mb-4">
    <div class="d-flex align-items-center gap-2 mb-3">
        <a href="{{ route('schools.index') }}" class="btn btn-icon btn-outline-secondary btn-sm" wire:navigate>
            <i class="ri ri-arrow-left-line"></i>
        </a>
        <div>
            <h4 class="mb-0">{{ $school->name }}</h4>
            <span class="text-body-secondary small">{{ $school->code }}</span>
        </div>
    </div>

    <ul class="nav nav-tabs">
        @foreach ($tabs as $key => $tab)
            <li class="nav-item">
                <a href="{{ route($tab['route'], $school) }}" class="nav-link {{ $active === $key ? 'active' : '' }}" wire:navigate>
                    {{ $tab['label'] }}
                </a>
            </li>
        @endforeach
    </ul>
</div>
