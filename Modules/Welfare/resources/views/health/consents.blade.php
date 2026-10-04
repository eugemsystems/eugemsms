<div>
    <h4 class="mb-1">{{ __('Medical consents') }}</h4>
    <p class="text-body-secondary mb-4">{{ __('Only a guardian holding may_authorise_medical on an active relationship may grant consent (BR-BRD-06-011).') }}</p>

    <div class="row g-4">
        <div class="col-md-7">
            <div class="card">
                <div class="card-header">{{ __('Consents') }}</div>
                <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        <thead><tr><th>{{ __('Student') }}</th><th>{{ __('Type') }}</th><th>{{ __('Status') }}</th><th></th></tr></thead>
                        <tbody>
                            @forelse ($consents as $consent)
                                <tr wire:key="consent-{{ $consent->id }}">
                                    <td>{{ $consent->student?->first_name }} {{ $consent->student?->last_name }}</td>
                                    <td>{{ str_replace('_', ' ', $consent->consent_type) }}</td>
                                    <td>
                                        @if ($consent->withdrawn_at)
                                            <span class="badge text-bg-danger">{{ __('Withdrawn') }}</span>
                                        @elseif ($consent->isValidNow())
                                            <span class="badge text-bg-success">{{ __('Valid') }}</span>
                                        @else
                                            <span class="badge text-bg-secondary">{{ __('Not yet / expired') }}</span>
                                        @endif
                                    </td>
                                    <td class="text-end">
                                        @if (! $consent->withdrawn_at)
                                            <button type="button" class="btn btn-sm btn-outline-danger" wire:click="$set('withdrawingConsentId', {{ $consent->id }})">{{ __('Withdraw') }}</button>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="4" class="text-center text-body-secondary py-3">{{ __('No consents recorded.') }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            @if ($withdrawingConsentId)
                <div class="card mt-3 border-danger">
                    <div class="card-body">
                        <input type="text" class="form-control mb-2" wire:model="withdrawReason" placeholder="{{ __('Withdrawal reason') }}">
                        <button type="button" class="btn btn-danger btn-sm" wire:click="withdraw({{ $withdrawingConsentId }})">{{ __('Confirm withdrawal') }}</button>
                    </div>
                </div>
            @endif
        </div>

        <div class="col-md-5">
            <div class="card">
                <div class="card-header">{{ __('Grant consent') }}</div>
                <div class="card-body">
                    <select class="form-select mb-2" wire:model.live="studentId">
                        <option value="">{{ __('Student') }}</option>
                        @foreach ($students as $student)
                            <option value="{{ $student->id }}">{{ $student->first_name }} {{ $student->last_name }}</option>
                        @endforeach
                    </select>
                    <select class="form-select mb-2" wire:model="guardianId">
                        <option value="">{{ __('Guardian') }}</option>
                        @foreach ($guardians as $guardian)
                            <option value="{{ $guardian->id }}">{{ $guardian->first_name }} {{ $guardian->last_name }}</option>
                        @endforeach
                    </select>
                    <select class="form-select mb-2" wire:model="consentType">
                        <option value="routine_treatment">{{ __('Routine treatment') }}</option>
                        <option value="otc_medication">{{ __('OTC medication') }}</option>
                        <option value="prescribed_medication">{{ __('Prescribed medication') }}</option>
                        <option value="emergency_treatment">{{ __('Emergency treatment') }}</option>
                        <option value="hospital_transfer">{{ __('Hospital transfer') }}</option>
                        <option value="dental">{{ __('Dental') }}</option>
                        <option value="immunisation">{{ __('Immunisation') }}</option>
                        <option value="information_sharing">{{ __('Information sharing') }}</option>
                    </select>
                    <input type="text" class="form-control mb-2" wire:model="scopeDetail" placeholder="{{ __('Scope detail (optional)') }}">
                    <select class="form-select mb-2" wire:model="grantedVia">
                        <option value="portal">{{ __('Portal') }}</option>
                        <option value="form">{{ __('Form') }}</option>
                        <option value="verbal_recorded">{{ __('Verbal (recorded)') }}</option>
                    </select>
                    <input type="date" class="form-control mb-2" wire:model="effectiveFrom">
                    <input type="date" class="form-control mb-2" wire:model="effectiveTo">
                    <div class="form-check mb-2">
                        <input type="checkbox" class="form-check-input" id="granted" wire:model="granted">
                        <label class="form-check-label" for="granted">{{ __('Granted') }}</label>
                    </div>
                    <button type="button" class="btn btn-primary btn-sm" wire:click="grant">{{ __('Record consent') }}</button>
                </div>
            </div>
        </div>
    </div>
</div>
