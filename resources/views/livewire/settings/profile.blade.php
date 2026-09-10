<section class="w-100">
    @include('partials.settings-heading')

    <h2 class="visually-hidden">{{ __('Profile settings') }}</h2>

    <x-settings.layout :heading="__('Profile')" :subheading="__('Update your name and email address')">
        <form wire:submit="updateProfileInformation" class="my-4 w-100">
            <div class="form-floating mb-3">
                <input wire:model="name" type="text" class="form-control @error('name') is-invalid @enderror" id="settings-name" required autofocus autocomplete="name">
                <label for="settings-name">{{ __('Name') }}</label>
                @error('name')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>

            <div class="mb-3">
                <div class="form-floating">
                    <input wire:model="email" type="email" class="form-control @error('email') is-invalid @enderror" id="settings-email" required autocomplete="email">
                    <label for="settings-email">{{ __('Email') }}</label>
                    @error('email')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                @if ($this->hasUnverifiedEmail)
                    <p class="small mt-2 mb-0">
                        {{ __('Your email address is unverified.') }}
                        <a href="javascript:void(0)" wire:click.prevent="resendVerificationNotification">
                            {{ __('Click here to re-send the verification email.') }}
                        </a>
                    </p>
                @endif
            </div>

            <button type="submit" class="btn btn-primary">{{ __('Save') }}</button>
        </form>

        @if ($this->showDeleteUser)
            <livewire:settings.delete-user-form />
        @endif
    </x-settings.layout>
</section>
