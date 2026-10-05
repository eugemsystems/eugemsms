<div>
    <h4 class="mb-3">{{ __('Scheduled reports') }}</h4>
    <p class="text-body-secondary small">{{ __('Records the schedule. Automated delivery is not wired up yet in this pass.') }}</p>

    <div class="row g-4">
        <div class="col-lg-6">
            <div class="card mb-3">
                <div class="card-header">{{ __('Report definitions') }}</div>
                <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        <thead><tr><th>{{ __('Code') }}</th><th>{{ __('Name') }}</th><th>{{ __('Type') }}</th></tr></thead>
                        <tbody>
                            @forelse ($definitions as $definition)
                                <tr wire:key="definition-{{ $definition->id }}">
                                    <td>{{ $definition->code }}</td>
                                    <td>{{ $definition->name }}</td>
                                    <td>{{ $definition->report_type }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="3" class="text-center text-body-secondary py-3">{{ __('No report definitions yet.') }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="card-body">
                    <input type="text" class="form-control mb-2" wire:model="definitionCode" placeholder="{{ __('Code') }}">
                    <input type="text" class="form-control mb-2" wire:model="definitionName" placeholder="{{ __('Name') }}">
                    <select class="form-select mb-2" wire:model="reportType">
                        <option value="trial_balance">{{ __('Trial balance') }}</option>
                        <option value="income_statement">{{ __('Income statement') }}</option>
                        <option value="balance_sheet">{{ __('Balance sheet') }}</option>
                        <option value="cash_flow">{{ __('Cash flow') }}</option>
                        <option value="departmental">{{ __('Departmental') }}</option>
                        <option value="custom">{{ __('Custom') }}</option>
                    </select>
                    <button type="button" class="btn btn-outline-primary btn-sm" wire:click="createDefinition">{{ __('Create definition') }}</button>
                </div>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="card">
                <div class="card-header">{{ __('Schedules') }}</div>
                <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        <thead><tr><th>{{ __('Name') }}</th><th>{{ __('Frequency') }}</th><th>{{ __('Format') }}</th></tr></thead>
                        <tbody>
                            @forelse ($schedules as $schedule)
                                <tr wire:key="schedule-{{ $schedule->id }}">
                                    <td>{{ $schedule->name }}</td>
                                    <td>{{ $schedule->frequency }}</td>
                                    <td>{{ $schedule->format }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="3" class="text-center text-body-secondary py-3">{{ __('No schedules yet.') }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="card-body">
                    <select class="form-select mb-2" wire:model="reportDefinitionId">
                        <option value="">{{ __('Report definition') }}</option>
                        @foreach ($definitions as $definition)
                            <option value="{{ $definition->id }}">{{ $definition->code }} — {{ $definition->name }}</option>
                        @endforeach
                    </select>
                    <input type="text" class="form-control mb-2" wire:model="scheduleName" placeholder="{{ __('Schedule name') }}">
                    <select class="form-select mb-2" wire:model="frequency">
                        <option value="daily">{{ __('Daily') }}</option>
                        <option value="weekly">{{ __('Weekly') }}</option>
                        <option value="monthly">{{ __('Monthly') }}</option>
                        <option value="termly">{{ __('Termly') }}</option>
                    </select>
                    <select class="form-select mb-2" wire:model="format">
                        <option value="pdf">PDF</option>
                        <option value="excel">Excel</option>
                        <option value="both">{{ __('Both') }}</option>
                    </select>
                    <input type="text" class="form-control mb-2" wire:model="recipientEmails" placeholder="{{ __('Recipient emails, comma separated') }}">
                    <button type="button" class="btn btn-primary btn-sm" wire:click="createSchedule">{{ __('Create schedule') }}</button>
                </div>
            </div>
        </div>
    </div>
</div>
