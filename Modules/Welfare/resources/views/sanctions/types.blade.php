<div>
    <h4 class="mb-1">{{ __('Sanction types') }}</h4>

    <div class="row g-4">
        <div class="col-md-7">
            <div class="card">
                <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        <thead><tr><th>{{ __('Code') }}</th><th>{{ __('Name') }}</th><th>{{ __('Severity') }}</th><th>{{ __('Committee') }}</th><th>{{ __('Removes campus') }}</th></tr></thead>
                        <tbody>
                            @forelse ($types as $type)
                                <tr wire:key="type-{{ $type->id }}">
                                    <td>{{ $type->code }}</td>
                                    <td>{{ $type->name }}</td>
                                    <td>{{ $type->severity_level }}</td>
                                    <td>{{ $type->requires_committee ? __('Yes') : __('No') }}</td>
                                    <td>{{ $type->removes_from_campus ? __('Yes') : __('No') }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="text-center text-body-secondary py-3">{{ __('No sanction types.') }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <div class="col-md-5">
            <div class="card">
                <div class="card-header">{{ __('New sanction type') }}</div>
                <div class="card-body">
                    <input type="text" class="form-control mb-2" wire:model="code" placeholder="{{ __('Code') }}">
                    <input type="text" class="form-control mb-2" wire:model="name" placeholder="{{ __('Name') }}">
                    <input type="number" class="form-control mb-2" wire:model="severityLevel" min="1" max="6" placeholder="{{ __('Severity (1-6)') }}">
                    <input type="number" class="form-control mb-2" wire:model="maxDurationDays" placeholder="{{ __('Max duration (days, optional)') }}">
                    <input type="number" class="form-control mb-2" wire:model="appealWindowDays" placeholder="{{ __('Appeal window (days)') }}">
                    <div class="form-check mb-1">
                        <input type="checkbox" class="form-check-input" id="requiresGuardianMeeting" wire:model="requiresGuardianMeeting">
                        <label class="form-check-label" for="requiresGuardianMeeting">{{ __('Requires guardian meeting') }}</label>
                    </div>
                    <div class="form-check mb-1">
                        <input type="checkbox" class="form-check-input" id="requiresCommittee" wire:model="requiresCommittee">
                        <label class="form-check-label" for="requiresCommittee">{{ __('Requires disciplinary committee') }}</label>
                    </div>
                    <div class="form-check mb-1">
                        <input type="checkbox" class="form-check-input" id="removesFromLessons" wire:model="removesFromLessons">
                        <label class="form-check-label" for="removesFromLessons">{{ __('Removes from lessons') }}</label>
                    </div>
                    <div class="form-check mb-1">
                        <input type="checkbox" class="form-check-input" id="removesFromCampus" wire:model="removesFromCampus">
                        <label class="form-check-label" for="removesFromCampus">{{ __('Removes from campus (sets suspended roll status)') }}</label>
                    </div>
                    <div class="form-check mb-2">
                        <input type="checkbox" class="form-check-input" id="appealable" wire:model="appealable">
                        <label class="form-check-label" for="appealable">{{ __('Appealable') }}</label>
                    </div>
                    <button type="button" class="btn btn-primary btn-sm" wire:click="create">{{ __('Create') }}</button>
                </div>
            </div>
        </div>
    </div>
</div>
