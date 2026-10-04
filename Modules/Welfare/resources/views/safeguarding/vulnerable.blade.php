<div>
    <h4 class="mb-1">{{ __('Vulnerable learner register') }}</h4>

    <div class="row g-4">
        <div class="col-md-7">
            <div class="card">
                <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        <thead><tr><th>{{ __('Student') }}</th><th>{{ __('Type') }}</th><th>{{ __('Next review') }}</th><th></th></tr></thead>
                        <tbody>
                            @forelse ($registrations as $registration)
                                <tr wire:key="vulnerable-{{ $registration->id }}">
                                    <td>{{ $registration->student?->first_name }} {{ $registration->student?->last_name }}</td>
                                    <td>{{ str_replace('_', ' ', $registration->vulnerability_type) }}</td>
                                    <td class="{{ $registration->next_review_on?->isPast() ? 'text-danger' : '' }}">{{ $registration->next_review_on?->toDateString() }}</td>
                                    <td>
                                        @if ($reviewingRegistrationId === $registration->id)
                                            <div class="d-flex gap-2">
                                                <input type="text" class="form-control form-control-sm" wire:model="updatedSupportPlan" placeholder="{{ __('Updated support plan (optional)') }}">
                                                <button type="button" class="btn btn-sm btn-success" wire:click="review({{ $registration->id }})">{{ __('Save') }}</button>
                                            </div>
                                        @else
                                            <button type="button" class="btn btn-sm btn-outline-primary" wire:click="$set('reviewingRegistrationId', {{ $registration->id }})">{{ __('Review') }}</button>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="4" class="text-center text-body-secondary py-3">{{ __('No active registrations.') }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <div class="col-md-5">
            <div class="card">
                <div class="card-header">{{ __('Add to register') }}</div>
                <div class="card-body">
                    <select class="form-select mb-2" wire:model="studentId">
                        <option value="">{{ __('Student') }}</option>
                        @foreach ($students as $student)
                            <option value="{{ $student->id }}">{{ $student->first_name }} {{ $student->last_name }}</option>
                        @endforeach
                    </select>
                    <select class="form-select mb-2" wire:model="vulnerabilityType">
                        <option value="orphan">{{ __('Orphan') }}</option>
                        <option value="child_headed_household">{{ __('Child-headed household') }}</option>
                        <option value="bereavement">{{ __('Bereavement') }}</option>
                        <option value="chronic_illness">{{ __('Chronic illness') }}</option>
                        <option value="financial_hardship">{{ __('Financial hardship') }}</option>
                        <option value="family_disruption">{{ __('Family disruption') }}</option>
                        <option value="refugee">{{ __('Refugee') }}</option>
                    </select>
                    <textarea class="form-control mb-2" wire:model="supportPlan" placeholder="{{ __('Support plan (optional)') }}"></textarea>
                    <input type="number" class="form-control mb-2" wire:model="assignedMentorId" placeholder="{{ __('Assigned mentor (staff id, optional)') }}">
                    <input type="number" class="form-control mb-2" wire:model="reviewFrequencyDays" placeholder="{{ __('Review frequency (days)') }}">
                    <button type="button" class="btn btn-primary btn-sm" wire:click="add">{{ __('Add') }}</button>
                </div>
            </div>
        </div>
    </div>
</div>
