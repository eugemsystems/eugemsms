<x-layouts::auth :title="__('Log in')" illustration="login">
    <h4 class="mb-1">{{ __('Welcome to :app! 👋', ['app' => config('app.name')]) }}</h4>
    <p class="mb-4">{{ __('Please sign in to your account and start the adventure') }}</p>

    <x-auth-session-status class="mb-4" :status="session('status')" />

    <x-passkey-verify />

    <form id="formAuthentication" method="POST" action="{{ route('login.store') }}">
        @csrf

        <div class="form-floating form-floating-outline mb-4">
            <input
                type="email"
                class="form-control @error('email') is-invalid @enderror"
                id="email"
                name="email"
                value="{{ old('email') }}"
                placeholder="{{ __('Enter your email') }}"
                required
                autofocus
                autocomplete="email"
            >
            <label for="email">{{ __('Email address') }}</label>
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
                            autocomplete="current-password"
                        >
                        <label for="password">{{ __('Password') }}</label>
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

        <div class="mb-4 d-flex justify-content-between">
            <div class="form-check">
                <input class="form-check-input" type="checkbox" id="remember-me" name="remember" {{ old('remember') ? 'checked' : '' }}>
                <label class="form-check-label" for="remember-me">{{ __('Remember me') }}</label>
            </div>
            @if (Route::has('password.request'))
                <a href="{{ route('password.request') }}" wire:navigate>{{ __('Forgot password?') }}</a>
            @endif
        </div>

        <button class="btn btn-primary d-grid w-100 mb-4" type="submit" data-test="login-button">{{ __('Log in') }}</button>
    </form>

    <p class="text-center mb-0">
        <span>{{ __("New to :app?", ['app' => config('app.name')]) }}</span>
        <a href="{{ route('register') }}" wire:navigate>
            <span>{{ __('Create an account') }}</span>
        </a>
    </p>
</x-layouts::auth>
