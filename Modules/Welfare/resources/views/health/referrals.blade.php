<div>
    <h4 class="mb-1">{{ __('External referrals') }}</h4>
    <p class="text-body-secondary mb-4">{{ __('Make → return → charge (only when guardian-borne, after approval). Closes the hospital roll status.') }}</p>

    <div class="row g-4">
        <div class="col-md-7">
            @forelse ($referrals as $referral)
                <div class="card mb-2" wire:key="referral-{{ $referral->id }}">
                    <div class="card-body py-2">
                        <div class="d-flex justify-content-between">
                            <span>{{ $referral->student?->first_name }} {{ $referral->student?->last_name }} — {{ $referral->facility_name }}</span>
                            <span class="badge text-bg-{{ in_array($referral->status, ['referred', 'in_transit', 'at_facility', 'admitted'], true) ? 'warning' : 'success' }}">{{ str_replace('_', ' ', $referral->status) }}</span>
                        </div>
                        <div class="small text-body-secondary">{{ $referral->referral_type }} · {{ $referral->urgency }}</div>

                        @if ($referral->status !== 'returned')
                            @if ($returningReferralId === $referral->id)
                                <div class="mt-2 d-flex gap-2">
                                    <input type="text" class="form-control form-control-sm" wire:model="returnOutcome" placeholder="{{ __('Outcome') }}">
                                    <button type="button" class="btn btn-sm btn-success" wire:click="recordReturn({{ $referral->id }})">{{ __('Confirm') }}</button>
                                </div>
                            @else
                                <button type="button" class="btn btn-sm btn-outline-success mt-2" wire:click="$set('returningReferralId', {{ $referral->id }})">{{ __('Record return') }}</button>
                            @endif
                        @endif

                        @if ($referral->cost_borne_by === 'guardian' && $referral->ad_hoc_charge_id === null)
                            @if ($chargingReferralId === $referral->id)
                                <div class="mt-2 d-flex gap-2">
                                    <select class="form-select form-select-sm" wire:model="feeComponentId">
                                        <option value="">{{ __('Fee component') }}</option>
                                        @foreach ($feeComponents as $component)
                                            <option value="{{ $component->id }}">{{ $component->name }}</option>
                                        @endforeach
                                    </select>
                                    <input type="number" class="form-control form-control-sm" wire:model="costMinor" placeholder="{{ __('Cost (minor units)') }}">
                                    <button type="button" class="btn btn-sm btn-success" wire:click="charge({{ $referral->id }})">{{ __('Charge') }}</button>
                                </div>
                            @else
                                <button type="button" class="btn btn-sm btn-outline-warning mt-2" wire:click="$set('chargingReferralId', {{ $referral->id }})">{{ __('Charge guardian') }}</button>
                            @endif
                        @endif
                    </div>
                </div>
            @empty
                <p class="text-body-secondary">{{ __('No referrals.') }}</p>
            @endforelse
        </div>

        <div class="col-md-5">
            <div class="card">
                <div class="card-header">{{ __('Make referral') }}</div>
                <div class="card-body">
                    <select class="form-select mb-2" wire:model="studentId">
                        <option value="">{{ __('Student') }}</option>
                        @foreach ($students as $student)
                            <option value="{{ $student->id }}">{{ $student->first_name }} {{ $student->last_name }}</option>
                        @endforeach
                    </select>
                    <select class="form-select mb-2" wire:model="referralType">
                        <option value="hospital">{{ __('Hospital') }}</option>
                        <option value="clinic">{{ __('Clinic') }}</option>
                        <option value="specialist">{{ __('Specialist') }}</option>
                        <option value="dentist">{{ __('Dentist') }}</option>
                        <option value="optician">{{ __('Optician') }}</option>
                        <option value="physio">{{ __('Physio') }}</option>
                    </select>
                    <input type="text" class="form-control mb-2" wire:model="facilityName" placeholder="{{ __('Facility name') }}">
                    <textarea class="form-control mb-2" wire:model="reason" placeholder="{{ __('Reason') }}"></textarea>
                    <select class="form-select mb-2" wire:model="urgency">
                        <option value="routine">{{ __('Routine') }}</option>
                        <option value="urgent">{{ __('Urgent') }}</option>
                        <option value="emergency">{{ __('Emergency') }}</option>
                    </select>
                    <select class="form-select mb-2" wire:model="transportMethod">
                        <option value="">{{ __('Transport method (optional)') }}</option>
                        <option value="school_vehicle">{{ __('School vehicle') }}</option>
                        <option value="ambulance">{{ __('Ambulance') }}</option>
                        <option value="guardian">{{ __('Guardian') }}</option>
                        <option value="taxi">{{ __('Taxi') }}</option>
                    </select>
                    <button type="button" class="btn btn-primary btn-sm" wire:click="make">{{ __('Refer') }}</button>
                </div>
            </div>
        </div>
    </div>
</div>
