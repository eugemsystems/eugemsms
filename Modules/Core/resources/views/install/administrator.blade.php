<div>
    @if (! $adminCreated)
        <div class="mb-4">
            <h4 class="mb-1">{{ __('Administrator') }}</h4>
            <p class="text-body-secondary mb-0">{{ __('Create the super administrator account.') }}</p>
        </div>

        <form wire:submit="createAdmin">
            <div class="form-floating form-floating-outline mb-4">
                <input type="text" class="form-control @error('name') is-invalid @enderror" id="name" wire:model="name" placeholder=" " autofocus>
                <label for="name">{{ __('Name') }}</label>
                @error('name')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>

            <div class="form-floating form-floating-outline mb-4">
                <input type="email" class="form-control @error('email') is-invalid @enderror" id="email" wire:model="email" placeholder=" ">
                <label for="email">{{ __('Email address') }}</label>
                @error('email')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>

            <div class="mb-4">
                <div class="input-group input-group-merge">
                    <div class="form-floating form-floating-outline">
                        <input type="password" class="form-control @error('password') is-invalid @enderror" id="admin-password" wire:model="password" placeholder=" ">
                        <label for="admin-password">{{ __('Password') }}</label>
                    </div>
                    <span class="input-group-text cursor-pointer" data-password-toggle="#admin-password"><i class="ri ri-eye-off-line"></i></span>
                </div>
                @error('password')
                    <div class="text-danger small mt-1">{{ $message }}</div>
                @enderror
            </div>

            <div class="mb-4">
                <div class="input-group input-group-merge">
                    <div class="form-floating form-floating-outline">
                        <input type="password" class="form-control" id="admin-password-confirmation" wire:model="password_confirmation" placeholder=" ">
                        <label for="admin-password-confirmation">{{ __('Confirm password') }}</label>
                    </div>
                    <span class="input-group-text cursor-pointer" data-password-toggle="#admin-password-confirmation"><i class="ri ri-eye-off-line"></i></span>
                </div>
            </div>

            <button type="submit" class="btn btn-primary d-grid w-100">{{ __('Create administrator') }}</button>
        </form>
    @elseif (! $twoFactorEnabled)
        <div class="text-center mb-4">
            <div class="d-inline-flex align-items-center justify-content-center rounded-circle bg-primary-subtle mb-3" style="width:3.5rem;height:3.5rem;">
                <i class="ri ri-shield-keyhole-line fs-3 text-primary"></i>
            </div>
            <h4 class="mb-1">{{ __('Secure your account') }}</h4>
            <p class="text-body-secondary mb-0">
                {{ __('Two-factor authentication is required for the super administrator and cannot be skipped. Scan the QR code with your authenticator app, then enter the 6-digit code.') }}
            </p>
        </div>

        @error('setupData')
            <div class="alert alert-danger d-flex align-items-center gap-2">
                <i class="ri ri-error-warning-line"></i> {{ $message }}
            </div>
        @enderror

        @if ($qrCodeSvg)
            <div class="d-flex justify-content-center mb-4">
                <div class="serp-qr-code bg-white p-3 border rounded" style="width: 12rem; height: 12rem;">
                    {!! $qrCodeSvg !!}
                </div>
            </div>

            <p class="text-center small text-body-secondary mb-4">
                {{ __('or enter this key manually:') }}
                <code>{{ $manualSetupKey }}</code>
            </p>
        @endif

        <form wire:submit="confirmTwoFactor">
            <div class="mb-4">
                <input
                    type="text"
                    inputmode="numeric"
                    maxlength="6"
                    wire:model="code"
                    class="form-control form-control-lg text-center mx-auto @error('code') is-invalid @enderror"
                    style="max-width: 16rem; letter-spacing: 0.5em; font-size: 1.5rem;"
                >
                @error('code')
                    <div class="invalid-feedback text-center">{{ $message }}</div>
                @enderror
            </div>

            <button type="submit" class="btn btn-primary d-grid w-100">{{ __('Confirm') }}</button>
        </form>
    @else
        <div class="text-center mb-4">
            <div class="d-inline-flex align-items-center justify-content-center rounded-circle bg-success-subtle mb-3" style="width:3.5rem;height:3.5rem;">
                <i class="ri ri-checkbox-circle-line fs-3 text-success"></i>
            </div>
            <h4 class="mb-1">{{ __('Two-factor authentication enabled') }}</h4>
            <p class="text-body-secondary mb-0">{{ __(':name is ready to go.', ['name' => $name]) }}</p>
        </div>

        <button type="button" class="btn btn-primary d-grid w-100" wire:click="continue">{{ __('Continue') }}</button>
    @endif
</div>
