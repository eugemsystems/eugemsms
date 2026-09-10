<x-layouts::auth :title="__('Two-factor authentication')" illustration="register">
    <div
        x-data="{
            showRecoveryInput: @js($errors->has('recovery_code')),
            digits: ['', '', '', '', '', ''],
            get code() {
                return this.digits.join('');
            },
            focusField() {
                this.$nextTick(() => (this.showRecoveryInput ? this.$refs.recoveryField : this.$refs.otp0)?.focus());
            },
            init() {
                this.focusField();
            },
            toggleInput() {
                this.showRecoveryInput = !this.showRecoveryInput;
                this.digits = ['', '', '', '', '', ''];
                this.focusField();
            },
            onDigitInput(index, event) {
                const value = event.target.value.replace(/\D/g, '').slice(-1);
                this.digits[index] = value;
                if (value && index < 5) {
                    this.$refs['otp' + (index + 1)]?.focus();
                }
            },
            onDigitKeydown(index, event) {
                if (event.key === 'Backspace' && !this.digits[index] && index > 0) {
                    this.$refs['otp' + (index - 1)]?.focus();
                }
            },
            onPaste(event) {
                const pasted = (event.clipboardData.getData('text') || '').replace(/\D/g, '').slice(0, 6).split('');
                pasted.forEach((digit, i) => (this.digits[i] = digit));
                event.preventDefault();
            },
        }"
    >
        <div x-show="!showRecoveryInput" x-cloak>
            <h4 class="mb-1">{{ __('Two-factor authentication 💬') }}</h4>
            <p class="mb-4">{{ __('Enter the authentication code provided by your authenticator application.') }}</p>
        </div>

        <div x-show="showRecoveryInput" x-cloak>
            <h4 class="mb-1">{{ __('Recovery code') }}</h4>
            <p class="mb-4">{{ __('Please confirm access to your account by entering one of your emergency recovery codes.') }}</p>
        </div>

        <form method="POST" action="{{ route('two-factor.login.store') }}">
            @csrf

            <div x-show="!showRecoveryInput" x-cloak class="mb-4">
                <div class="auth-input-wrapper d-flex align-items-center justify-content-between" x-on:paste="onPaste">
                    @for ($i = 0; $i < 6; $i++)
                        <input
                            type="tel"
                            inputmode="numeric"
                            maxlength="1"
                            class="form-control auth-input text-center mx-1 my-2 @error('code') is-invalid @enderror"
                            x-ref="otp{{ $i }}"
                            x-bind:value="digits[{{ $i }}]"
                            x-on:input="onDigitInput({{ $i }}, $event)"
                            x-on:keydown="onDigitKeydown({{ $i }}, $event)"
                        >
                    @endfor
                </div>
                <input type="hidden" name="code" x-bind:value="code">
                @error('code')
                    <div class="text-danger small mt-1">{{ $message }}</div>
                @enderror
            </div>

            <div x-show="showRecoveryInput" x-cloak class="mb-4">
                <div class="form-floating form-floating-outline">
                    <input
                        type="text"
                        name="recovery_code"
                        id="recovery_code"
                        x-ref="recoveryField"
                        x-bind:required="showRecoveryInput"
                        autocomplete="one-time-code"
                        placeholder="{{ __('Enter a recovery code') }}"
                        class="form-control @error('recovery_code') is-invalid @enderror"
                    >
                    <label for="recovery_code">{{ __('Recovery code') }}</label>
                </div>
                @error('recovery_code')
                    <div class="text-danger small mt-1">{{ $message }}</div>
                @enderror
            </div>

            <button type="submit" class="btn btn-primary d-grid w-100 mb-4">{{ __('Continue') }}</button>

            <div class="text-center">
                <span>{{ __('or you can') }}</span>
                <a href="javascript:void(0)" @click="toggleInput()">
                    <span x-show="!showRecoveryInput" x-cloak>{{ __('login using a recovery code') }}</span>
                    <span x-show="showRecoveryInput" x-cloak>{{ __('login using an authentication code') }}</span>
                </a>
            </div>
        </form>
    </div>
</x-layouts::auth>
