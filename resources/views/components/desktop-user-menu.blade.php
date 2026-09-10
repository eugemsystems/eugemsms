@props(['name'])

<div {{ $attributes->class('dropdown w-100 px-3 pb-3') }}>
    <button type="button" class="btn btn-outline-secondary d-flex align-items-center gap-2 w-100 text-start" data-bs-toggle="dropdown" aria-expanded="false" data-test="sidebar-menu-button">
        <span class="avatar avatar-sm rounded-circle bg-primary text-white d-flex align-items-center justify-content-center flex-shrink-0" style="width:1.75rem;height:1.75rem;font-size:.75rem;">
            {{ auth()->user()->initials() }}
        </span>
        <span class="text-truncate flex-fill fw-medium">{{ $name }}</span>
        <i class="ri ri-expand-up-down-line"></i>
    </button>

    <div class="dropdown-menu w-100 shadow-sm">
        <div class="d-flex align-items-center gap-2 px-3 py-2">
            <span class="avatar avatar-sm rounded-circle bg-primary text-white d-flex align-items-center justify-content-center flex-shrink-0" style="width:2rem;height:2rem;font-size:.8rem;">
                {{ auth()->user()->initials() }}
            </span>
            <div class="text-truncate">
                <div class="fw-medium text-truncate">{{ auth()->user()->name }}</div>
                <div class="small text-body-secondary text-truncate">{{ auth()->user()->email }}</div>
            </div>
        </div>

        <div class="dropdown-divider"></div>

        <a class="dropdown-item d-flex align-items-center gap-2" href="{{ route('profile.edit') }}" wire:navigate>
            <i class="ri ri-settings-3-line"></i> {{ __('Settings') }}
        </a>

        <div class="dropdown-divider"></div>

        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit" class="dropdown-item d-flex align-items-center gap-2" data-test="logout-button">
                <i class="ri ri-logout-box-r-line"></i> {{ __('Log out') }}
            </button>
        </form>
    </div>
</div>
