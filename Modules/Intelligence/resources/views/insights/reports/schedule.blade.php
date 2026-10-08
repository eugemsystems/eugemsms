<div>
    <div class="d-flex align-items-center gap-2 mb-4">
        <a href="{{ route('insights.reports.index', $school) }}" class="btn btn-icon btn-outline-secondary btn-sm" wire:navigate>
            <i class="ri ri-arrow-left-line"></i>
        </a>
        <div>
            <h4 class="mb-0">{{ __('Schedule a report') }}</h4>
            <p class="text-body-secondary small mb-0">{{ __('Recipients are told by email when the report is ready; no file is attached yet.') }}</p>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-lg-7">
            <div class="card">
                <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        <thead><tr><th>{{ __('Report') }}</th><th>{{ __('Frequency') }}</th><th>{{ __('Format') }}</th><th class="text-end">{{ __('Recipients') }}</th><th>{{ __('Next run') }}</th><th>{{ __('Status') }}</th><th></th></tr></thead>
                        <tbody>
                            @forelse ($schedules as $schedule)
                                <tr wire:key="sch-{{ $schedule->id }}">
                                    <td>{{ $schedule->report?->name }}</td>
                                    <td>{{ $schedule->frequency }}</td>
                                    <td>{{ $schedule->format }}</td>
                                    <td class="text-end">{{ count($schedule->recipients ?? []) }}</td>
                                    <td class="small">{{ $schedule->next_run_at?->toDayDateTimeString() ?? '—' }}</td>
                                    <td><span class="badge text-bg-{{ $schedule->is_active ? 'success' : 'secondary' }}">{{ $schedule->is_active ? __('Active') : __('Paused') }}</span></td>
                                    <td class="text-end text-nowrap">
                                        <button type="button" class="btn btn-xs btn-outline-secondary" wire:click="pause({{ $schedule->id }})">{{ $schedule->is_active ? __('Pause') : __('Resume') }}</button>
                                        <button type="button" class="btn btn-xs btn-outline-danger" wire:click="delete({{ $schedule->id }})" wire:confirm="{{ __('Delete this schedule?') }}">{{ __('Delete') }}</button>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="7" class="text-center text-body-secondary py-3">{{ __('No schedules yet.') }}</td></tr>
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
                    <select class="form-select mb-2" wire:model="reportId">
                        <option value="">{{ __('One of my reports…') }}</option>
                        @foreach ($reports as $report) <option value="{{ $report->id }}">{{ $report->name }}</option> @endforeach
                    </select>
                    @error('reportId') <div class="text-danger small mb-2">{{ $message }}</div> @enderror
                    <div class="row g-2 mb-2">
                        <div class="col-6"><select class="form-select" wire:model="frequency"><option value="daily">{{ __('Daily') }}</option><option value="weekly">{{ __('Weekly') }}</option><option value="monthly">{{ __('Monthly') }}</option><option value="termly">{{ __('Termly') }}</option></select></div>
                        <div class="col-6"><select class="form-select" wire:model="format"><option value="csv">CSV</option><option value="excel">Excel</option><option value="pdf">PDF</option></select></div>
                    </div>
                    <label class="form-label small mb-0">{{ __('Recipients') }}</label>
                    <select class="form-select mb-2" multiple size="6" wire:model="recipientIds">
                        @foreach ($colleagues as $colleague) <option value="{{ $colleague->id }}">{{ $colleague->name }}</option> @endforeach
                    </select>
                    @error('recipientIds') <div class="text-danger small mb-2">{{ $message }}</div> @enderror
                    <button type="button" class="btn btn-primary btn-sm" wire:click="schedule">{{ __('Schedule') }}</button>
                </div>
            </div>
        </div>
    </div>
</div>
