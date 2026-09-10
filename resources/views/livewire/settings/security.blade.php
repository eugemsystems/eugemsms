<section class="w-100">
    @include('partials.settings-heading')

    <h2 class="visually-hidden">{{ __('Security settings') }}</h2>

    <x-settings.layout :heading="__('Update password')" :subheading="__('Ensure your account is using a long, random password to stay secure')">
        <form method="POST" wire:submit="updatePassword" class="mt-4">
            <div class="mb-3">
                <div class="input-group input-group-merge">
                    <div class="form-floating">
                        <input wire:model="current_password" type="password" class="form-control @error('current_password') is-invalid @enderror" id="current_password" required autocomplete="current-password" placeholder="{{ __('Current password') }}">
                        <label for="current_password">{{ __('Current password') }}</label>
                    </div>
                    <span class="input-group-text cursor-pointer" data-password-toggle="#current_password"><i class="ri ri-eye-off-line"></i></span>
                </div>
                @error('current_password')
                    <div class="text-danger small mt-1">{{ $message }}</div>
                @enderror
            </div>

            <div class="mb-3">
                <div class="input-group input-group-merge">
                    <div class="form-floating">
                        <input wire:model="password" type="password" class="form-control @error('password') is-invalid @enderror" id="new_password" required autocomplete="new-password" placeholder="{{ __('New password') }}">
                        <label for="new_password">{{ __('New password') }}</label>
                    </div>
                    <span class="input-group-text cursor-pointer" data-password-toggle="#new_password"><i class="ri ri-eye-off-line"></i></span>
                </div>
                @error('password')
                    <div class="text-danger small mt-1">{{ $message }}</div>
                @enderror
            </div>

            <div class="mb-3">
                <div class="input-group input-group-merge">
                    <div class="form-floating">
                        <input wire:model="password_confirmation" type="password" class="form-control" id="new_password_confirmation" required autocomplete="new-password" placeholder="{{ __('Confirm password') }}">
                        <label for="new_password_confirmation">{{ __('Confirm password') }}</label>
                    </div>
                    <span class="input-group-text cursor-pointer" data-password-toggle="#new_password_confirmation"><i class="ri ri-eye-off-line"></i></span>
                </div>
            </div>

            <button type="submit" class="btn btn-primary" data-test="update-password-button">{{ __('Save') }}</button>
        </form>

        @if ($canManageTwoFactor)
            <section class="mt-5 pt-3 border-top">
                <h5>{{ __('Two-factor authentication') }}</h5>
                <p class="text-body-secondary">{{ __('Manage your two-factor authentication settings') }}</p>

                <div class="w-100">
                    @if ($twoFactorEnabled)
                        <p class="small text-body-secondary">
                            {{ __('You will be prompted for a secure, random pin during login, which you can retrieve from the TOTP-supported application on your phone.') }}
                        </p>

                        <button type="button" class="btn btn-danger" wire:click="disable">
                            {{ __('Disable 2FA') }}
                        </button>

                        <div class="mt-4">
                            <livewire:settings.two-factor.recovery-codes :$requiresConfirmation />
                        </div>
                    @else
                        <p class="small text-body-secondary">
                            {{ __('When you enable two-factor authentication, you will be prompted for a secure pin during login. This pin can be retrieved from a TOTP-supported application on your phone.') }}
                        </p>

                        <button type="button" class="btn btn-primary" wire:click="enable">
                            {{ __('Enable 2FA') }}
                        </button>
                    @endif
                </div>
            </section>

            @if ($showModal)
                <div class="modal show d-block" tabindex="-1" style="background: rgba(0, 0, 0, .5);">
                    <div class="modal-dialog modal-dialog-centered">
                        <div class="modal-content">
                            <div class="modal-body p-4">
                                <div class="text-center mb-4">
                                    @if (! $showVerificationStep)
                                        <div class="d-inline-flex align-items-center justify-content-center rounded-circle bg-primary-subtle mb-3" style="width:3.5rem;height:3.5rem;">
                                            <i class="ri ri-qr-code-line fs-3 text-primary"></i>
                                        </div>
                                    @endif
                                    <h5 class="mb-1">{{ $this->modalConfig['title'] }}</h5>
                                    <p class="text-body-secondary mb-0">{{ $this->modalConfig['description'] }}</p>
                                </div>

                                @if ($showVerificationStep)
                                    <div
                                        x-data
                                        x-init="$nextTick(() => $el.querySelector('input')?.focus())"
                                        class="d-flex flex-column align-items-center gap-3"
                                    >
                                        <input
                                            type="text"
                                            wire:model="code"
                                            inputmode="numeric"
                                            maxlength="6"
                                            class="form-control form-control-lg text-center @error('code') is-invalid @enderror"
                                            style="max-width: 16rem; letter-spacing: 0.5em; font-size: 1.5rem;"
                                        >
                                        @error('code')
                                            <div class="invalid-feedback text-center">{{ $message }}</div>
                                        @enderror
                                    </div>

                                    <div class="d-flex gap-2 mt-4">
                                        <button type="button" class="btn btn-outline-secondary flex-fill" wire:click="resetVerification">
                                            {{ __('Back') }}
                                        </button>
                                        <button type="button" class="btn btn-primary flex-fill" wire:click="confirmTwoFactor">
                                            {{ __('Confirm') }}
                                        </button>
                                    </div>
                                @else
                                    @error('setupData')
                                        <div class="alert alert-danger d-flex align-items-center gap-2">
                                            <i class="ri ri-error-warning-line"></i> {{ $message }}
                                        </div>
                                    @enderror

                                    <div class="d-flex justify-content-center mb-4">
                                        <div class="border rounded position-relative overflow-hidden" style="width: 16rem; aspect-ratio: 1;">
                                            @empty($qrCodeSvg)
                                                <div class="position-absolute top-0 start-0 w-100 h-100 d-flex align-items-center justify-content-center bg-body-tertiary">
                                                    <span class="spinner-border text-primary" role="status" aria-hidden="true"></span>
                                                </div>
                                            @else
                                                <div class="serp-qr-code bg-white p-3 d-flex align-items-center justify-content-center h-100">
                                                    {!! $qrCodeSvg !!}
                                                </div>
                                            @endempty
                                        </div>
                                    </div>

                                    <button
                                        type="button"
                                        {{ $errors->has('setupData') ? 'disabled' : '' }}
                                        class="btn btn-primary d-grid w-100 mb-4"
                                        wire:click="showVerificationIfNecessary"
                                    >
                                        {{ $this->modalConfig['buttonText'] }}
                                    </button>

                                    <div class="text-center small text-body-secondary mb-2">{{ __('or, enter the code manually') }}</div>

                                    <div
                                        class="input-group"
                                        x-data="{
                                            copied: false,
                                            async copy() {
                                                try {
                                                    await navigator.clipboard.writeText('{{ $manualSetupKey }}');
                                                    this.copied = true;
                                                    setTimeout(() => this.copied = false, 1500);
                                                } catch (e) {}
                                            },
                                        }"
                                    >
                                        @empty($manualSetupKey)
                                            <div class="form-control text-center bg-body-tertiary">
                                                <span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span>
                                            </div>
                                        @else
                                            <input type="text" readonly value="{{ $manualSetupKey }}" class="form-control">
                                            <button type="button" class="btn btn-outline-secondary" x-on:click="copy()">
                                                <i class="ri ri-file-copy-line" x-show="!copied"></i>
                                                <i class="ri ri-check-line text-success" x-show="copied" x-cloak></i>
                                            </button>
                                        @endempty
                                    </div>
                                @endif
                            </div>
                            @unless ($showVerificationStep)
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-outline-secondary" wire:click="closeModal">{{ __('Close') }}</button>
                                </div>
                            @endunless
                        </div>
                    </div>
                </div>
            @endif
        @endif

        @if ($canManagePasskeys)
            <section class="mt-5 pt-3 border-top">
                <h5>{{ __('Passkeys') }}</h5>
                <p class="text-body-secondary">{{ __('Manage your passkeys for passwordless sign-in') }}</p>

                <div class="border rounded mb-3">
                    @forelse ($passkeys as $passkey)
                        <div class="d-flex align-items-center justify-content-between p-3 {{ ! $loop->last ? 'border-bottom' : '' }}">
                            <div class="d-flex align-items-center gap-3">
                                <div class="d-flex align-items-center justify-content-center rounded bg-body-tertiary flex-shrink-0" style="width:2.5rem;height:2.5rem;">
                                    <i class="ri ri-key-2-line text-body-secondary"></i>
                                </div>
                                <div>
                                    <div class="d-flex align-items-center gap-2">
                                        <span class="fw-medium">{{ $passkey['name'] }}</span>
                                        @if ($passkey['authenticator'])
                                            <span class="badge text-bg-secondary">{{ $passkey['authenticator'] }}</span>
                                        @endif
                                    </div>
                                    <p class="text-body-secondary small mb-0">
                                        {{ __('Added :time', ['time' => $passkey['created_at_diff']]) }}
                                        @if ($passkey['last_used_at_diff'])
                                            <span class="mx-1">/</span>
                                            {{ __('Last used :time', ['time' => $passkey['last_used_at_diff']]) }}
                                        @endif
                                    </p>
                                </div>
                            </div>

                            <button type="button" class="btn btn-sm btn-outline-danger" wire:click="confirmDelete({{ $passkey['id'] }})">
                                <i class="ri ri-delete-bin-line"></i>
                            </button>
                        </div>
                    @empty
                        <div class="p-4 text-center">
                            <div class="d-inline-flex align-items-center justify-content-center rounded-circle bg-body-tertiary mb-3" style="width:3.5rem;height:3.5rem;">
                                <i class="ri ri-key-2-line fs-3 text-body-secondary"></i>
                            </div>
                            <p class="fw-medium mb-1">{{ __('No passkeys yet') }}</p>
                            <p class="text-body-secondary small mb-0">{{ __('Add a passkey to sign in without a password') }}</p>
                        </div>
                    @endforelse
                </div>

                <x-passkey-registration />
            </section>
        @endif
    </x-settings.layout>

    @if ($showDeleteModal)
        <div class="modal show d-block" tabindex="-1" style="background: rgba(0, 0, 0, .5);">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-body p-4">
                        <h5 class="mb-2">{{ __('Remove passkey') }}</h5>
                        <p class="text-body-secondary">
                            {{ __('Are you sure you want to remove the passkey ":name"? You will no longer be able to use it to sign in.', ['name' => $deletingPasskeyName]) }}
                        </p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" wire:click="closeDeleteModal">{{ __('Cancel') }}</button>
                        <button type="button" class="btn btn-danger" wire:click="deletePasskey">{{ __('Remove passkey') }}</button>
                    </div>
                </div>
            </div>
        </div>
    @endif
</section>
