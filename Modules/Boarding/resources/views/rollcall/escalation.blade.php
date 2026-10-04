<div>
    <h4 class="mb-1">{{ __('Escalation profiles & roll call points') }}</h4>
    <p class="text-body-secondary mb-4">{{ __('The missing-learner ladder. Build it exactly as specified — this is the part that matters when something has gone wrong.') }}</p>

    <div class="row g-4">
        <div class="col-md-7">
            <div class="card mb-4">
                <div class="card-header">{{ __('Escalation profiles') }}</div>
                <div class="list-group list-group-flush">
                    @forelse ($profiles as $profile)
                        <div class="list-group-item">
                            <strong>{{ $profile->name }}</strong> @if ($profile->is_default) <span class="badge text-bg-primary">{{ __('default') }}</span> @endif
                            <ol class="small mb-0 mt-1">
                                @foreach ($profile->steps->sortBy('step_number') as $step)
                                    <li>T+{{ $step->delay_minutes }}m — {{ implode('/', $step->channels) }} @if ($step->requires_action_record) <span class="text-danger">({{ __('action record required') }})</span> @endif</li>
                                @endforeach
                            </ol>
                        </div>
                    @empty
                        <div class="list-group-item text-body-secondary">{{ __('No profiles yet.') }}</div>
                    @endforelse
                </div>
            </div>

            <div class="card">
                <div class="card-header">{{ __('Roll call points') }}</div>
                <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        <thead><tr><th>{{ __('Code') }}</th><th>{{ __('Name') }}</th><th>{{ __('Time') }}</th><th>{{ __('Hostel') }}</th><th>{{ __('Profile') }}</th></tr></thead>
                        <tbody>
                            @forelse ($points as $point)
                                <tr><td>{{ $point->code }}</td><td>{{ $point->name }}</td><td>{{ $point->scheduled_time }}</td><td>{{ $point->hostel?->code ?? __('All') }}</td><td>{{ $point->escalationProfile?->name ?? '—' }}</td></tr>
                            @empty
                                <tr><td colspan="5" class="text-center text-body-secondary py-3">{{ __('No points configured.') }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="col-md-5">
            <div class="card mb-4">
                <div class="card-header">{{ __('New escalation profile (standard 5-step ladder)') }}</div>
                <div class="card-body">
                    <input type="text" class="form-control mb-2" wire:model="profileName" placeholder="{{ __('Profile name') }}">
                    <div class="form-check mb-2">
                        <input type="checkbox" class="form-check-input" wire:model="isDefault" id="isDefault">
                        <label class="form-check-label" for="isDefault">{{ __('Default profile') }}</label>
                    </div>
                    <p class="small text-body-secondary">{{ __('Uses the spec\'s standard T+0/10/20/30/45 ladder with action records mandatory on steps 1-2.') }}</p>
                    <button type="button" class="btn btn-primary btn-sm" wire:click="createProfile">{{ __('Create profile') }}</button>
                </div>
            </div>

            <div class="card">
                <div class="card-header">{{ __('New roll call point') }}</div>
                <div class="card-body">
                    <input type="text" class="form-control mb-2" wire:model="pointCode" placeholder="{{ __('Code (e.g. LIGHTS_OUT)') }}">
                    <input type="text" class="form-control mb-2" wire:model="pointName" placeholder="{{ __('Name') }}">
                    <input type="time" class="form-control mb-2" wire:model="pointScheduledTime">
                    <select class="form-select mb-2" wire:model="pointHostelId">
                        <option value="">{{ __('All hostels') }}</option>
                        @foreach ($hostels as $hostel)
                            <option value="{{ $hostel->id }}">{{ $hostel->name }}</option>
                        @endforeach
                    </select>
                    <select class="form-select mb-2" wire:model="pointEscalationProfileId">
                        <option value="">{{ __('No escalation profile') }}</option>
                        @foreach ($profiles as $profile)
                            <option value="{{ $profile->id }}">{{ $profile->name }}</option>
                        @endforeach
                    </select>
                    <button type="button" class="btn btn-primary btn-sm" wire:click="createPoint">{{ __('Create point') }}</button>
                </div>
            </div>
        </div>
    </div>
</div>
