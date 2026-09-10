<x-layouts::auth :title="__('Reset password')" illustration="reset-password">
    <h4 class="mb-1">{{ __('Reset password 🔒') }}</h4>
    <p class="mb-4">{{ __('Your new password must be different from previously used passwords') }}</p>

    <x-auth-session-status class="mb-4" :status="session('status')" />

    <form id="formAuthentication" method="POST" action="{{ route('password.update') }}">
        @csrf
        <input type="hidden" name="token" value="{{ request()->route('token') }}">

        <div class="form-floating form-floating-outline mb-4">
            <input
                type="email"
                class="form-control @error('email') is-invalid @enderror"
                id="email"
                name="email"
                value="{{ request('email') }}"
                placeholder="{{ __('Enter your email') }}"
                required
                autocomplete="email"
            >
            <label for="email">{{ __('Email') }}</label>
            @error('email')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>

        <div class="mb-4">
            <div class="form-password-toggle">
                <div class="input-group input-group-merge">
                    <div class="form-floating form-floating-outline">
                        <input
                            type="password"
                            id="password"
                            class="form-control @error('password') is-invalid @enderror"
                            name="password"
                            placeholder="&#183;&#183;&#183;&#183;&#183;&#183;&#183;&#183;&#183;&#183;&#183;&#183;"
                            required
                            autocomplete="new-password"
                        >
                        <label for="password">{{ __('New password') }}</label>
                    </div>
                    <span class="input-group-text cursor-pointer" data-password-toggle="#password">
                        <i class="icon-base ri ri-eye-off-line icon-20px"></i>
                    </span>
                </div>
            </div>
            @error('password')
                <div class="text-danger small mt-1">{{ $message }}</div>
            @enderror
        </div>

        <div class="mb-4">
            <div class="form-password-toggle">
                <div class="input-group input-group-merge">
                    <div class="form-floating form-floating-outline">
                        <input
                            type="password"
                            id="password_confirmation"
                            class="form-control"
                            name="password_confirmation"
                            placeholder="&#183;&#183;&#183;&#183;&#183;&#183;&#183;&#183;&#183;&#183;&#183;&#183;"
                            required
                            autocomplete="new-password"
                        >
                        <label for="password_confirmation">{{ __('Confirm password') }}</label>
                    </div>
                    <span class="input-group-text cursor-pointer" data-password-toggle="#password_confirmation">
                        <i class="icon-base ri ri-eye-off-line icon-20px"></i>
                    </span>
                </div>
            </div>
        </div>

        <button class="btn btn-primary d-grid w-100 mb-4" type="submit" data-test="reset-password-button">
            {{ __('Set new password') }}
        </button>

        <div class="text-center">
            <a href="{{ route('login') }}" class="d-flex align-items-center justify-content-center" wire:navigate>
                <i class="icon-base ri ri-arrow-left-s-line icon-20px me-1"></i>
                {{ __('Back to login') }}
            </a>
        </div>
    </form>
</x-layouts::auth>
