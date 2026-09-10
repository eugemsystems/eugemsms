<x-layouts::auth :title="__('Confirm password')" illustration="login">
    <h4 class="mb-1">{{ __('Confirm password 🔒') }}</h4>
    <p class="mb-4">{{ __('This is a secure area of the application. Please confirm your password before continuing.') }}</p>

    <x-auth-session-status class="mb-4" :status="session('status')" />

    <x-passkey-verify
        options-route="passkey.confirm-options"
        submit-route="passkey.confirm"
        :label="__('Confirm with passkey')"
        :loading-label="__('Confirming...')"
        :separator="__('Or confirm with password')"
    />

    <form id="formAuthentication" method="POST" action="{{ route('password.confirm.store') }}">
        @csrf

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

        <button class="btn btn-primary d-grid w-100" type="submit" data-test="confirm-password-button">
            {{ __('Confirm') }}
        </button>
    </form>
</x-layouts::auth>
