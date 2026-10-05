<div>
    <h4 class="mb-1">{{ __('Activities') }}</h4>

    <div class="row g-4">
        <div class="col-lg-7">
            <div class="card">
                <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        <thead><tr><th>{{ __('Code') }}</th><th>{{ __('Name') }}</th><th>{{ __('Type') }}</th><th>{{ __('Season') }}</th><th>{{ __('Clearance') }}</th></tr></thead>
                        <tbody>
                            @forelse ($activities as $activity)
                                <tr wire:key="activity-{{ $activity->id }}">
                                    <td>{{ $activity->code }}</td>
                                    <td>{{ $activity->name }}</td>
                                    <td>{{ $activity->activity_type }}</td>
                                    <td>{{ $activity->season ?? '—' }}</td>
                                    <td>{{ $activity->requires_medical_clearance ? __('Required') : '—' }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="text-center text-body-secondary py-3">{{ __('No activities.') }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <div class="col-lg-5">
            <div class="card">
                <div class="card-header">{{ __('New activity') }}</div>
                <div class="card-body">
                    <input type="text" class="form-control mb-2" wire:model="code" placeholder="{{ __('Code') }}">
                    <input type="text" class="form-control mb-2" wire:model="name" placeholder="{{ __('Name') }}">
                    <select class="form-select mb-2" wire:model="activityType">
                        @foreach (['sport', 'club', 'society', 'cultural', 'service'] as $type)
                            <option value="{{ $type }}">{{ $type }}</option>
                        @endforeach
                    </select>
                    <input type="text" class="form-control mb-2" wire:model="season" placeholder="{{ __('Season (optional)') }}">
                    <select class="form-select mb-2" wire:model="genderScope">
                        <option value="both">{{ __('Both') }}</option>
                        <option value="male">{{ __('Male') }}</option>
                        <option value="female">{{ __('Female') }}</option>
                    </select>
                    <input type="number" class="form-control mb-2" wire:model="maxParticipants" placeholder="{{ __('Max participants (optional)') }}">
                    <div class="form-check mb-2">
                        <input type="checkbox" class="form-check-input" id="requiresMedicalClearance" wire:model="requiresMedicalClearance">
                        <label class="form-check-label" for="requiresMedicalClearance">{{ __('Requires medical clearance') }}</label>
                    </div>
                    <div class="form-check mb-2">
                        <input type="checkbox" class="form-check-input" id="requiresGuardianConsent" wire:model="requiresGuardianConsent">
                        <label class="form-check-label" for="requiresGuardianConsent">{{ __('Requires guardian consent') }}</label>
                    </div>
                    <button type="button" class="btn btn-primary btn-sm" wire:click="create">{{ __('Create activity') }}</button>
                </div>
            </div>
        </div>
    </div>
</div>
