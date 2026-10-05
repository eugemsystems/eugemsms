<div>
    <h4 class="mb-1">{{ __('Processing register') }} 🇿🇼</h4>

    <div class="row g-4">
        <div class="col-lg-7">
            <div class="card">
                <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        <thead><tr><th>{{ __('Activity') }}</th><th>{{ __('Basis') }}</th><th>{{ __('Owning module') }}</th><th>{{ __('Minors') }}</th><th>{{ __('Special category') }}</th></tr></thead>
                        <tbody>
                            @forelse ($entries as $entry)
                                <tr wire:key="pr-{{ $entry->id }}">
                                    <td>{{ $entry->activity_name }}</td>
                                    <td>{{ $entry->lawful_basis }}</td>
                                    <td>{{ $entry->owning_module }}</td>
                                    <td>{{ $entry->involves_minors ? __('Yes') : __('No') }}</td>
                                    <td>{{ $entry->is_special_category ? __('Yes') : __('No') }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="text-center text-body-secondary py-3">{{ __('No activities registered yet.') }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <div class="col-lg-5">
            <div class="card">
                <div class="card-header">{{ __('Register activity') }}</div>
                <div class="card-body">
                    <input type="text" class="form-control mb-2" wire:model="activityName" placeholder="{{ __('Activity name') }}">
                    <textarea class="form-control mb-2" wire:model="purpose" placeholder="{{ __('Purpose') }}"></textarea>
                    <select class="form-select mb-2" wire:model="lawfulBasis">
                        <option value="consent">{{ __('Consent') }}</option>
                        <option value="contract">{{ __('Contract') }}</option>
                        <option value="legal_obligation">{{ __('Legal obligation') }}</option>
                        <option value="vital_interest">{{ __('Vital interest') }}</option>
                        <option value="legitimate_interest">{{ __('Legitimate interest') }}</option>
                    </select>
                    <input type="text" class="form-control mb-2" wire:model="dataCategoriesText" placeholder="{{ __('Data categories, comma-separated') }}">
                    <input type="text" class="form-control mb-2" wire:model="subjectCategoriesText" placeholder="{{ __('Subject categories, comma-separated') }}">
                    <input type="text" class="form-control mb-2" wire:model="owningModule" placeholder="{{ __('Owning module code, e.g. PPL-01') }}">
                    <div class="form-check mb-1">
                        <input type="checkbox" class="form-check-input" wire:model="involvesMinors" id="procMinors">
                        <label class="form-check-label small" for="procMinors">{{ __('Involves minors') }}</label>
                    </div>
                    <div class="form-check mb-2">
                        <input type="checkbox" class="form-check-input" wire:model="isSpecialCategory" id="procSpecial">
                        <label class="form-check-label small" for="procSpecial">{{ __('Special category (medical, biometric)') }}</label>
                    </div>
                    <button type="button" class="btn btn-primary btn-sm" wire:click="register">{{ __('Register') }}</button>
                </div>
            </div>
        </div>
    </div>
</div>
