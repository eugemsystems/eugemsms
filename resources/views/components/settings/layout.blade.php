<div class="d-flex flex-column flex-md-row align-items-start">
    <div class="me-md-5 w-100 pb-4" style="max-width: 220px;">
        <div class="nav flex-column nav-pills">
            <a class="nav-link {{ request()->routeIs('profile.edit') ? 'active' : '' }}" href="{{ route('profile.edit') }}" wire:navigate>{{ __('Profile') }}</a>
            <a class="nav-link {{ request()->routeIs('security.edit') ? 'active' : '' }}" href="{{ route('security.edit') }}" wire:navigate>{{ __('Security') }}</a>
            <a class="nav-link {{ request()->routeIs('appearance.edit') ? 'active' : '' }}" href="{{ route('appearance.edit') }}" wire:navigate>{{ __('Appearance') }}</a>
        </div>
    </div>

    <div class="flex-fill w-100">
        <h5 class="mb-0">{{ $heading ?? '' }}</h5>
        <p class="text-body-secondary">{{ $subheading ?? '' }}</p>

        <div class="mt-4" style="max-width: 32rem;">
            {{ $slot }}
        </div>
    </div>
</div>
