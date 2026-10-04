<div>
    <h4 class="mb-1">{{ __('Clinical record') }} — {{ $student->first_name }} {{ $student->last_name }}</h4>
    <p class="text-body-secondary mb-4">{{ __('Tier 3. Every view of this screen is logged (AC-BRD-06-002).') }}</p>

    <div class="row g-4">
        <div class="col-md-7">
            @if ($record)
                <div class="card mb-3">
                    <div class="card-header">{{ __('Medical record') }}</div>
                    <div class="card-body small">
                        <div><strong>{{ __('Blood group') }}:</strong> {{ $record->blood_group ?? '—' }}</div>
                        <div><strong>{{ __('Medical aid') }}:</strong> {{ $record->medical_aid_provider ?? '—' }}</div>
                        <div><strong>{{ __('Family doctor') }}:</strong> {{ $record->family_doctor_name ?? '—' }} {{ $record->family_doctor_phone }}</div>
                        <div><strong>{{ __('Preferred hospital') }}:</strong> {{ $record->preferred_hospital ?? '—' }}</div>
                        @if ($record->notes)
                            <div class="mt-2"><strong>{{ __('Notes') }}:</strong> {{ $record->notes }}</div>
                        @endif
                    </div>
                </div>
            @endif

            <div class="card">
                <div class="card-header">{{ __('Conditions') }}</div>
                <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        <thead><tr><th>{{ __('Name') }}</th><th>{{ __('Type') }}</th><th>{{ __('Severity') }}</th><th>{{ __('Verified') }}</th><th></th></tr></thead>
                        <tbody>
                            @forelse ($conditions as $condition)
                                <tr wire:key="condition-{{ $condition->id }}">
                                    <td>{{ $condition->name }}</td>
                                    <td>{{ str_replace('_', ' ', $condition->condition_type) }}</td>
                                    <td><span class="badge text-bg-{{ in_array($condition->severity, ['life_threatening', 'severe'], true) ? 'danger' : 'secondary' }}">{{ str_replace('_', ' ', $condition->severity) }}</span></td>
                                    <td>{{ $condition->verified_by_nurse ? __('Yes') : __('No') }}</td>
                                    <td class="text-end">
                                        @unless ($condition->verified_by_nurse)
                                            <button type="button" class="btn btn-sm btn-outline-success" wire:click="verify({{ $condition->id }})">{{ __('Verify') }}</button>
                                        @endunless
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="text-center text-body-secondary py-3">{{ __('No conditions declared.') }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="col-md-5">
            <div class="card">
                <div class="card-header">{{ __('Declare condition') }}</div>
                <div class="card-body">
                    <select class="form-select mb-2" wire:model="conditionType">
                        <option value="allergy">{{ __('Allergy') }}</option>
                        <option value="chronic">{{ __('Chronic') }}</option>
                        <option value="disability">{{ __('Disability') }}</option>
                        <option value="mental_health">{{ __('Mental health') }}</option>
                        <option value="temporary">{{ __('Temporary') }}</option>
                    </select>
                    <input type="text" class="form-control mb-2" wire:model="conditionName" placeholder="{{ __('Condition name') }}">
                    <select class="form-select mb-2" wire:model="severity">
                        <option value="mild">{{ __('Mild') }}</option>
                        <option value="moderate">{{ __('Moderate') }}</option>
                        <option value="severe">{{ __('Severe') }}</option>
                        <option value="life_threatening">{{ __('Life threatening') }}</option>
                    </select>
                    <input type="text" class="form-control mb-2" wire:model="category" placeholder="{{ __('Category (optional, e.g. anaphylaxis)') }}">
                    <select class="form-select mb-2" wire:model="declaredBy">
                        <option value="nurse">{{ __('Nurse') }}</option>
                        <option value="doctor">{{ __('Doctor') }}</option>
                        <option value="guardian">{{ __('Guardian') }}</option>
                        <option value="admission">{{ __('Admission') }}</option>
                    </select>
                    <input type="date" class="form-control mb-2" wire:model="effectiveFrom">

                    <div class="form-check mb-1">
                        <input type="checkbox" class="form-check-input" id="affectsDietary" wire:model.live="affectsDietary">
                        <label class="form-check-label" for="affectsDietary">{{ __('Affects dietary requirements') }}</label>
                    </div>
                    @if ($affectsDietary)
                        <input type="text" class="form-control mb-2" wire:model="publicSummary" placeholder="{{ __('Public summary — e.g. \'Severe nut allergy — EpiPen\'') }}">
                    @endif

                    <div class="form-check mb-1">
                        <input type="checkbox" class="form-check-input" id="affectsAccommodation" wire:model.live="affectsAccommodation">
                        <label class="form-check-label" for="affectsAccommodation">{{ __('Affects boarding accommodation') }}</label>
                    </div>
                    @if ($affectsAccommodation)
                        <input type="text" class="form-control mb-2" wire:model="accommodationRequirement" placeholder="{{ __('e.g. \'ground floor\', \'near exit\'') }}">
                    @endif

                    <div class="form-check mb-1">
                        <input type="checkbox" class="form-check-input" id="affectsPhysicalActivity" wire:model="affectsPhysicalActivity">
                        <label class="form-check-label" for="affectsPhysicalActivity">{{ __('Affects physical activity') }}</label>
                    </div>
                    <div class="form-check mb-2">
                        <input type="checkbox" class="form-check-input" id="requiresEmergencyPlan" wire:model="requiresEmergencyPlan">
                        <label class="form-check-label" for="requiresEmergencyPlan">{{ __('Requires emergency care plan') }}</label>
                    </div>

                    <textarea class="form-control mb-2" wire:model="diagnosisNotes" placeholder="{{ __('Diagnosis notes (Tier 3 only, optional)') }}"></textarea>

                    <button type="button" class="btn btn-primary btn-sm" wire:click="declare">{{ __('Declare') }}</button>
                </div>
            </div>
        </div>
    </div>
</div>
