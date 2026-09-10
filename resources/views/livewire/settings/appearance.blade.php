<section class="w-100" x-data x-on:theme-updated.window="window.applySerpTheme($event.detail.theme)">
    @include('partials.settings-heading')

    <h2 class="visually-hidden">{{ __('Appearance settings') }}</h2>

    <x-settings.layout :heading="__('Appearance')" :subheading="__('Update the appearance settings for your account')">
        <div class="btn-group" role="group" aria-label="{{ __('Appearance') }}">
            <button type="button" class="btn btn-outline-primary d-inline-flex align-items-center gap-1 {{ $theme === 'light' ? 'active' : '' }}" wire:click="updateTheme('light')">
                <i class="ri ri-sun-line"></i> {{ __('Light') }}
            </button>
            <button type="button" class="btn btn-outline-primary d-inline-flex align-items-center gap-1 {{ $theme === 'dark' ? 'active' : '' }}" wire:click="updateTheme('dark')">
                <i class="ri ri-moon-line"></i> {{ __('Dark') }}
            </button>
            <button type="button" class="btn btn-outline-primary d-inline-flex align-items-center gap-1 {{ $theme === 'system' ? 'active' : '' }}" wire:click="updateTheme('system')">
                <i class="ri ri-computer-line"></i> {{ __('System') }}
            </button>
        </div>
        <p class="text-body-secondary small mt-2 mb-0">{{ __('Saved to your account — applies wherever you sign in.') }}</p>
    </x-settings.layout>
</section>
