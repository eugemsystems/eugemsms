<div>
    <div class="mb-4">
        <h4 class="mb-1">{{ __('Baseline Seed') }}</h4>
        <p class="text-body-secondary mb-0">{{ __('Zimbabwe baseline packs to seed for the new school.') }}</p>
    </div>

    <div class="d-flex flex-column gap-2 mb-4">
        @foreach ($this->packs as $code => $pack)
            <label class="border rounded p-3 d-flex align-items-start gap-3 {{ $pack->isAvailable() ? '' : 'opacity-50' }}">
                <input
                    type="checkbox"
                    class="form-check-input mt-1"
                    value="{{ $code }}"
                    wire:model="selectedPacks"
                    @disabled(! $pack->isAvailable())
                >
                <span>
                    <span class="d-flex align-items-center gap-2">
                        <span class="fw-medium">{{ $pack->label() }}</span>
                        @unless ($pack->isAvailable())
                            <span class="badge text-bg-secondary">{{ __('coming soon') }}</span>
                        @endunless
                    </span>
                    <span class="d-block small text-body-secondary">{{ $pack->description() }}</span>
                </span>
            </label>
        @endforeach
    </div>

    <button type="button" class="btn btn-primary d-grid w-100" wire:click="continue">{{ __('Continue') }}</button>
</div>
