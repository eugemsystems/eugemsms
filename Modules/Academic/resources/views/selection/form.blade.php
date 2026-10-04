<div>
    <h4 class="mb-1">{{ __('Subject selection') }}</h4>
    <p class="text-body-secondary mb-4">{{ $student->first_name }} {{ $student->last_name }}</p>

    <div class="card">
        <div class="card-header">{{ __('Choose subjects') }}</div>
        <div class="card-body">
            <div class="row g-1 mb-3" style="max-height: 260px; overflow-y: auto;">
                @foreach ($subjects as $subject)
                    <div class="col-md-6">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" wire:model.live="selectedSubjectIds" value="{{ $subject->id }}" id="selection-subject-{{ $subject->id }}">
                            <label class="form-check-label" for="selection-subject-{{ $subject->id }}">{{ $subject->name }}</label>
                        </div>
                    </div>
                @endforeach
            </div>

            <button type="button" class="btn btn-outline-secondary btn-sm" wire:click="checkSelection">{{ __('Check selection') }}</button>

            @if ($validationPreview !== null)
                <div class="alert alert-{{ $validationPreview['isValid'] ? 'success' : 'danger' }} mt-3 mb-0">
                    <strong>{{ $validationPreview['isValid'] ? __('Selection passes.') : __('Selection blocked.') }}</strong>
                    @foreach ($validationPreview['blocks'] as $blockMessage)
                        <div>⛔ {{ $blockMessage }}</div>
                    @endforeach
                    @foreach ($validationPreview['warnings'] as $warningMessage)
                        <div>⚠ {{ $warningMessage }}</div>
                    @endforeach
                </div>
            @endif

            @if ($feePreview !== null && $feePreview['amountMinor'] !== null)
                <div class="alert alert-info mt-2 mb-0">
                    {{ __('Indicative termly fee:') }} <strong>{{ number_format($feePreview['amountMinor'] / 100, 2) }} {{ $feePreview['currency'] }}</strong>
                </div>
            @endif

            @if ($validationPreview !== null && $validationPreview['warnings'] !== [] && ! $validationPreview['isValid'] === false)
                <div class="form-check mt-2">
                    <input class="form-check-input" type="checkbox" wire:model="acknowledgeWarnings" id="selection-ack">
                    <label class="form-check-label" for="selection-ack">{{ __('I acknowledge the warnings above and want to proceed.') }}</label>
                </div>
            @endif

            @error('selectedSubjectIds') <div class="text-danger small mt-2">{{ $message }}</div> @enderror

            <button type="button" class="btn btn-primary mt-3" wire:click="submit">{{ __('Submit selection') }}</button>
        </div>
    </div>
</div>
