<div>
    <h4 class="mb-1">{{ __('Membership') }}</h4>

    <div class="row g-4">
        <div class="col-lg-7">
            <div class="card">
                <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        <thead><tr><th>{{ __('Activity') }}</th><th>{{ __('Student') }}</th><th>{{ __('Consent') }}</th><th>{{ __('Medical') }}</th><th>{{ __('Status') }}</th><th></th></tr></thead>
                        <tbody>
                            @forelse ($memberships as $membership)
                                <tr wire:key="membership-{{ $membership->id }}">
                                    <td>{{ $membership->activity->name }}</td>
                                    <td>{{ $membership->student->fullName() }}</td>
                                    <td>{{ $membership->consent_received ? __('Yes') : __('No') }}</td>
                                    <td>{{ $membership->medical_cleared === null ? '—' : ($membership->medical_cleared ? __('Cleared') : __('Not cleared')) }}</td>
                                    <td>{{ $membership->status }}</td>
                                    <td>
                                        @if ($membership->status === 'active')
                                            <button type="button" class="btn btn-outline-danger btn-sm" wire:click="withdraw({{ $membership->id }})">{{ __('Withdraw') }}</button>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="6" class="text-center text-body-secondary py-3">{{ __('No memberships.') }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <div class="col-lg-5">
            <div class="card">
                <div class="card-header">{{ __('New membership') }}</div>
                <div class="card-body">
                    <select class="form-select mb-2" wire:model="activityId">
                        <option value="">{{ __('Activity') }}</option>
                        @foreach ($activities as $activity)
                            <option value="{{ $activity->id }}">{{ $activity->name }}</option>
                        @endforeach
                    </select>
                    <select class="form-select mb-2" wire:model="studentId">
                        <option value="">{{ __('Student') }}</option>
                        @foreach ($students as $student)
                            <option value="{{ $student->id }}">{{ $student->fullName() }}</option>
                        @endforeach
                    </select>
                    <div class="form-check mb-2">
                        <input type="checkbox" class="form-check-input" id="consentReceived" wire:model="consentReceived">
                        <label class="form-check-label" for="consentReceived">{{ __('Guardian consent received') }}</label>
                    </div>
                    <div class="form-check mb-2">
                        <input type="checkbox" class="form-check-input" id="medicalCleared" wire:model="medicalCleared">
                        <label class="form-check-label" for="medicalCleared">{{ __('Medically cleared (if required)') }}</label>
                    </div>
                    <div class="form-check mb-2">
                        <input type="checkbox" class="form-check-input" id="overrideCapacity" wire:model="overrideCapacity">
                        <label class="form-check-label" for="overrideCapacity">{{ __('Override max participants') }}</label>
                    </div>
                    <button type="button" class="btn btn-primary btn-sm" wire:click="join">{{ __('Add member') }}</button>
                </div>
            </div>
        </div>
    </div>
</div>
