<div>
    <div class="d-flex align-items-center gap-2 mb-4">
        <a href="{{ route('finance.currency.rates', $school) }}" class="btn btn-icon btn-outline-secondary btn-sm" wire:navigate>
            <i class="ri ri-arrow-left-line"></i>
        </a>
        <div>
            <h4 class="mb-1">{{ __('Capture exchange rate') }}</h4>
            <p class="text-body-secondary mb-0">{{ __('A corrected rate is always a new row — the previous one is superseded, never edited.') }}</p>
        </div>
    </div>

    <div class="card">
        <div class="card-body">
            <form wire:submit="save">
                <div class="row g-3">
                    <div class="col-md-4">
                        <div class="form-floating form-floating-outline">
                            <select class="form-select @error('fromCurrency') is-invalid @enderror" id="fromCurrency" wire:model="fromCurrency">
                                <option value="USD">USD</option>
                                <option value="ZWG">ZWG</option>
                            </select>
                            <label for="fromCurrency">{{ __('From') }}</label>
                            @error('fromCurrency') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-floating form-floating-outline">
                            <select class="form-select @error('toCurrency') is-invalid @enderror" id="toCurrency" wire:model="toCurrency">
                                <option value="USD">USD</option>
                                <option value="ZWG">ZWG</option>
                            </select>
                            <label for="toCurrency">{{ __('To') }}</label>
                            @error('toCurrency') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-floating form-floating-outline">
                            <input type="text" inputmode="decimal" class="form-control @error('rate') is-invalid @enderror" id="rate" wire:model="rate" placeholder=" ">
                            <label for="rate">{{ __('Rate (1 From = ? To)') }}</label>
                            @error('rate') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                    </div>

                    <div class="col-md-6">
                        <div class="form-floating form-floating-outline">
                            <select class="form-select @error('sourceId') is-invalid @enderror" id="sourceId" wire:model="sourceId">
                                <option value="">{{ __('Select a source') }}</option>
                                @foreach ($sources as $source)
                                    <option value="{{ $source->id }}">{{ $source->name }} @if ($source->requires_approval) ({{ __('requires approval') }}) @endif</option>
                                @endforeach
                            </select>
                            <label for="sourceId">{{ __('Source') }}</label>
                            @error('sourceId') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-floating form-floating-outline">
                            <input type="datetime-local" class="form-control @error('effectiveFrom') is-invalid @enderror" id="effectiveFrom" wire:model="effectiveFrom" placeholder=" ">
                            <label for="effectiveFrom">{{ __('Effective from') }}</label>
                            @error('effectiveFrom') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                    </div>

                    <div class="col-12">
                        <div class="form-floating form-floating-outline">
                            <input type="text" class="form-control @error('notes') is-invalid @enderror" id="notes" wire:model="notes" placeholder=" ">
                            <label for="notes">{{ __('Notes (optional)') }}</label>
                            @error('notes') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                    </div>
                </div>

                <div class="mt-4 d-flex gap-2">
                    <button type="submit" class="btn btn-primary" wire:loading.attr="disabled">{{ __('Capture rate') }}</button>
                    <a href="{{ route('finance.currency.rates', $school) }}" class="btn btn-outline-secondary" wire:navigate>{{ __('Cancel') }}</a>
                </div>
            </form>
        </div>
    </div>
</div>
