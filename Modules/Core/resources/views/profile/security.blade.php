<div>
    <div class="mb-4">
        <h4 class="mb-1">{{ __('My security') }}</h4>
        <p class="text-body-secondary mb-0">{{ __('Manage your password, two-factor authentication, and signed-in devices.') }}</p>
    </div>

    <div class="row g-4">
        <div class="col-lg-6">
            <div class="card h-100">
                <div class="card-body">
                    <h5 class="card-title">{{ __('Change password') }}</h5>
                    <form wire:submit="changePassword">
                        <div class="form-floating form-floating-outline mb-3">
                            <input
                                type="password"
                                class="form-control @error('current_password') is-invalid @enderror"
                                id="current_password"
                                wire:model="current_password"
                                autocomplete="current-password"
                                placeholder=" "
                            >
                            <label for="current_password">{{ __('Current password') }}</label>
                            @error('current_password')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="form-floating form-floating-outline mb-3">
                            <input
                                type="password"
                                class="form-control @error('password') is-invalid @enderror"
                                id="password"
                                wire:model="password"
                                autocomplete="new-password"
                                placeholder=" "
                            >
                            <label for="password">{{ __('New password') }}</label>
                            @error('password')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="form-floating form-floating-outline mb-3">
                            <input
                                type="password"
                                class="form-control"
                                id="password_confirmation"
                                wire:model="password_confirmation"
                                autocomplete="new-password"
                                placeholder=" "
                            >
                            <label for="password_confirmation">{{ __('Confirm new password') }}</label>
                        </div>

                        <button type="submit" class="btn btn-primary" wire:loading.attr="disabled">
                            {{ __('Change password') }}
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-lg-6">
            <div class="card mb-4">
                <div class="card-body">
                    <h5 class="card-title">{{ __('Two-factor authentication') }}</h5>
                    <div class="d-flex align-items-center gap-2 mb-3">
                        @if ($isTwoFactorEnabled)
                            <i class="ri ri-shield-check-line text-success fs-4"></i>
                            <span>{{ __('Enabled') }}</span>
                        @else
                            <i class="ri ri-shield-line text-body-secondary fs-4"></i>
                            <span>{{ __('Not enabled') }}</span>
                        @endif
                    </div>
                    <a href="{{ route('two-factor.setup') }}" class="btn btn-outline-primary" wire:navigate>
                        {{ $isTwoFactorEnabled ? __('Manage two-factor authentication') : __('Set up two-factor authentication') }}
                    </a>
                </div>
            </div>

            <div class="card">
                <div class="card-body">
                    <h5 class="card-title">{{ __('Other sessions') }}</h5>
                    <p class="text-body-secondary">
                        {{ __('This signs you out of every other device using the mobile app or a browser session other than this one — it never signs out this admin session, and any device you use again will simply need to sign back in.') }}
                    </p>
                    <button
                        type="button"
                        class="btn btn-outline-danger"
                        wire:click="logOutOtherDevices"
                        wire:confirm="{{ __('Sign out of all other devices?') }}"
                    >
                        {{ __('Log out of all other devices') }}
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>
