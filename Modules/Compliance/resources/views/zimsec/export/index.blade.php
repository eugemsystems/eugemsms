<div>
    <h4 class="mb-1">{{ __('ZIMSEC export') }} 🇿🇼</h4>
    <p class="text-body-secondary small">{{ __('Export is refused while any candidate has a validation error. A human uploads the resulting file through ZIMSEC\'s own Online Candidate Registration System.') }}</p>

    <select class="form-select mb-3" style="max-width:420px" wire:model="registrationId">
        <option value="0">{{ __('Select registration') }}</option>
        @foreach ($registrations as $registration)
            <option value="{{ $registration->id }}">{{ $registration->exam_level }} — {{ $registration->exam_series }} ({{ $registration->status }})</option>
        @endforeach
    </select>

    @if ($selected)
        <div class="row g-4">
            <div class="col-lg-6">
                <div class="card">
                    <div class="card-header">{{ __('Export') }}</div>
                    <div class="card-body">
                        <p class="small mb-2">{{ __('Centre number:') }} {{ $selected->centre_number ?: __('missing') }}</p>
                        <p class="small mb-2">{{ __('Errors outstanding:') }} {{ $selected->error_count }}</p>
                        <div class="form-check mb-2">
                            <input type="checkbox" class="form-check-input" wire:model="acknowledgeWarnings" id="ackWarnings">
                            <label class="form-check-label small" for="ackWarnings">{{ __('Acknowledge outstanding warnings and export anyway') }}</label>
                        </div>
                        <button type="button" class="btn btn-primary btn-sm" wire:click="export">{{ __('Export registration') }}</button>
                    </div>
                </div>
            </div>
            <div class="col-lg-6">
                <div class="card">
                    <div class="card-header">{{ __('Record ZIMSEC submission') }}</div>
                    <div class="card-body">
                        <p class="small text-body-secondary">{{ __('Record once the exported file has been uploaded to ZIMSEC\'s portal.') }}</p>
                        <input type="text" class="form-control mb-2" wire:model="zimsecReference" placeholder="{{ __('ZIMSEC reference (optional)') }}">
                        <button type="button" class="btn btn-outline-primary btn-sm" wire:click="recordSubmission" @disabled($selected->status !== 'exported')>{{ __('Record submission') }}</button>
                    </div>
                </div>
            </div>
        </div>
    @else
        <p class="text-body-secondary">{{ __('Select a registration above.') }}</p>
    @endif
</div>
