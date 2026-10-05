<div>
    <h4 class="mb-1">{{ __('Preventive maintenance schedules') }}</h4>
    <p class="text-body-secondary mb-4">{{ __('Calendar- or usage-triggered preventive work, with a forecast of what\'s due.') }}</p>

    <button type="button" class="btn btn-outline-primary btn-sm mb-3" wire:click="generateDue">{{ __('Generate due work orders now') }}</button>

    <div class="row g-4">
        <div class="col-lg-7">
            <div class="card">
                <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        <thead><tr><th>{{ __('Name') }}</th><th>{{ __('Asset') }}</th><th>{{ __('Trigger') }}</th><th>{{ __('Next due') }}</th></tr></thead>
                        <tbody>
                            @forelse ($schedules as $schedule)
                                <tr wire:key="sched-{{ $schedule->id }}">
                                    <td>{{ $schedule->name }}</td>
                                    <td>{{ $schedule->maintenanceAsset->name }}</td>
                                    <td>{{ $schedule->trigger_type }}</td>
                                    <td>{{ $schedule->next_due_on?->toFormattedDateString() ?? $schedule->next_due_units }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="4" class="text-center text-body-secondary py-3">{{ __('No schedules.') }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <div class="col-lg-5">
            <div class="card">
                <div class="card-header">{{ __('New schedule') }}</div>
                <div class="card-body">
                    <select class="form-select mb-2" wire:model="maintenanceAssetId">
                        <option value="">{{ __('Asset') }}</option>
                        @foreach ($assets as $asset)
                            <option value="{{ $asset->id }}">{{ $asset->name }}</option>
                        @endforeach
                    </select>
                    <input type="text" class="form-control mb-2" wire:model="name" placeholder="{{ __('Schedule name, e.g. Generator 250-hour service') }}">
                    <select class="form-select mb-2" wire:model="triggerType">
                        <option value="calendar">{{ __('Calendar') }}</option>
                        <option value="usage">{{ __('Usage (km/hours)') }}</option>
                        <option value="both">{{ __('Both') }}</option>
                    </select>
                    <input type="number" class="form-control mb-2" wire:model="intervalDays" placeholder="{{ __('Interval (days, optional)') }}">
                    <input type="number" step="0.01" class="form-control mb-2" wire:model="intervalUnits" placeholder="{{ __('Interval (km/hours, optional)') }}">
                    <input type="number" class="form-control mb-2" wire:model="leadTimeDays" placeholder="{{ __('Lead time (days)') }}">
                    <select class="form-select mb-2" wire:model="assignedTeam">
                        <option value="in_house">{{ __('In-house') }}</option>
                        <option value="contractor">{{ __('Contractor') }}</option>
                    </select>
                    <textarea class="form-control mb-2" wire:model="taskChecklistText" rows="3" placeholder="{{ __('Task checklist, one per line') }}"></textarea>
                    <button type="button" class="btn btn-primary btn-sm" wire:click="create">{{ __('Create schedule') }}</button>
                </div>
            </div>
        </div>
    </div>
</div>
