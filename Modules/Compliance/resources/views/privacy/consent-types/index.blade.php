<div>
    <h4 class="mb-1">{{ __('Consent types') }} 🇿🇼</h4>

    <div class="row g-4">
        <div class="col-lg-7">
            <div class="card">
                <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        <thead><tr><th>{{ __('Code') }}</th><th>{{ __('Name') }}</th><th>{{ __('Basis') }}</th><th>{{ __('Applies to') }}</th><th>{{ __('Withdrawable') }}</th></tr></thead>
                        <tbody>
                            @forelse ($consentTypes as $type)
                                <tr wire:key="ct-{{ $type->id }}">
                                    <td>{{ $type->code }}</td>
                                    <td>{{ $type->name }}</td>
                                    <td>{{ $type->lawful_basis }}</td>
                                    <td>{{ $type->applies_to }}</td>
                                    <td>{{ $type->is_withdrawable ? __('Yes') : __('No') }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="text-center text-body-secondary py-3">{{ __('No consent types yet.') }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <div class="col-lg-5">
            <div class="card">
                <div class="card-header">{{ __('New consent type') }}</div>
                <div class="card-body">
                    <input type="text" class="form-control mb-2" wire:model="code" placeholder="{{ __('Code, e.g. photography') }}">
                    <input type="text" class="form-control mb-2" wire:model="name" placeholder="{{ __('Name') }}">
                    <textarea class="form-control mb-2" wire:model="description" placeholder="{{ __('Plain-language description') }}"></textarea>
                    <select class="form-select mb-2" wire:model="lawfulBasis">
                        <option value="consent">{{ __('Consent') }}</option>
                        <option value="contract">{{ __('Contract') }}</option>
                        <option value="legal_obligation">{{ __('Legal obligation') }}</option>
                        <option value="vital_interest">{{ __('Vital interest') }}</option>
                        <option value="legitimate_interest">{{ __('Legitimate interest') }}</option>
                    </select>
                    <select class="form-select mb-2" wire:model="appliesTo">
                        <option value="student">{{ __('Student') }}</option>
                        <option value="guardian">{{ __('Guardian') }}</option>
                        <option value="staff">{{ __('Staff') }}</option>
                    </select>
                    <div class="form-check mb-1">
                        <input type="checkbox" class="form-check-input" wire:model="isWithdrawable" id="ctWithdrawable">
                        <label class="form-check-label small" for="ctWithdrawable">{{ __('Withdrawable') }}</label>
                    </div>
                    <div class="form-check mb-2">
                        <input type="checkbox" class="form-check-input" wire:model="requiredForEnrolment" id="ctRequired">
                        <label class="form-check-label small" for="ctRequired">{{ __('Required for enrolment') }}</label>
                    </div>
                    <input type="number" class="form-control mb-2" wire:model="renewalFrequencyMonths" placeholder="{{ __('Renewal frequency (months, optional)') }}">
                    <button type="button" class="btn btn-primary btn-sm" wire:click="create">{{ __('Create') }}</button>
                </div>
            </div>
        </div>
    </div>
</div>
