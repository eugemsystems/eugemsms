<x-layouts::auth :title="__('Forgot password')" illustration="forgot-password">
    <h4 class="mb-1">{{ __('Forgot password? 🔒') }}</h4>
    <p class="mb-4">{{ __("Enter your email and we'll send you instructions to reset your password") }}</p>

    <x-auth-session-status class="mb-4" :status="session('status')" />

    <form id="formAuthentication" method="POST" action="{{ route('password.email') }}">
        @csrf

        <div class="form-floating form-floating-outline mb-4">
            <input
                type="email"
                class="form-control @error('email') is-invalid @enderror"
                id="email"
                name="email"
                placeholder="{{ __('Enter your email') }}"
                required
                autofocus
            >
            <label for="email">{{ __('Email address') }}</label>
            @error('email')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>

        <button class="btn btn-primary d-grid w-100 mb-4" type="submit" data-test="email-password-reset-link-button">
            {{ __('Send reset link') }}
        </button>
    </form>

    <div class="text-center">
        <a href="{{ route('login') }}" class="d-flex align-items-center justify-content-center" wire:navigate>
            <i class="icon-base ri ri-arrow-left-s-line icon-20px me-1"></i>
            {{ __('Back to login') }}
        </a>
    </div>
</x-layouts::auth>
