<x-layouts::auth :title="__('Register')" illustration="register">
    <h4 class="mb-1">{{ __('Start your adventure 🚀') }}</h4>
    <p class="mb-4">{{ __('Enter your details below to create your account') }}</p>

    <form id="formAuthentication" method="POST" action="{{ route('register.store') }}">
        @csrf

        <div class="form-floating form-floating-outline mb-4">
            <input
                type="text"
                class="form-control @error('name') is-invalid @enderror"
                id="name"
                name="name"
                value="{{ old('name') }}"
                placeholder="{{ __('Enter your full name') }}"
                required
                autofocus
                autocomplete="name"
            >
            <label for="name">{{ __('Name') }}</label>
            @error('name')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>

        <div class="form-floating form-floating-outline mb-4">
            <input
                type="email"
                class="form-control @error('email') is-invalid @enderror"
                id="email"
                name="email"
                value="{{ old('email') }}"
                placeholder="{{ __('Enter your email') }}"
                required
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
                            autocomplete="new-password"
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

        <button type="submit" class="btn btn-primary d-grid w-100 mb-4" data-test="register-user-button">
            {{ __('Create account') }}
        </button>
    </form>

    <p class="text-center mb-0">
        <span>{{ __('Already have an account?') }}</span>
        <a href="{{ route('login') }}" wire:navigate>
            <span>{{ __('Log in instead') }}</span>
        </a>
    </p>
</x-layouts::auth>
