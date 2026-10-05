<div>
    <h4 class="mb-1">{{ __('Patrols') }}</h4>

    <button type="button" class="btn btn-outline-warning btn-sm mb-3" wire:click="checkMissed">{{ __('Check missed patrols') }}</button>
    @if ($missedChecked)
        <div class="alert {{ $missedCount > 0 ? 'alert-warning' : 'alert-success' }} py-2">{{ __('Missed patrols found:') }} {{ $missedCount }}</div>
    @endif

    <div class="row g-4">
        <div class="col-lg-7">
            <div class="card mb-3">
                <div class="card-header">{{ __('Patrol routes') }}</div>
                <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        <thead><tr><th>{{ __('Code') }}</th><th>{{ __('Name') }}</th><th>{{ __('Checkpoints') }}</th><th>{{ __('Frequency') }}</th></tr></thead>
                        <tbody>
                            @forelse ($routes as $route)
                                <tr wire:key="route-{{ $route->id }}">
                                    <td>{{ $route->code }}</td>
                                    <td>{{ $route->name }}</td>
                                    <td>{{ count($route->checkpoint_ids) }}</td>
                                    <td>{{ $route->frequency }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="4" class="text-center text-body-secondary py-3">{{ __('No routes.') }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="card">
                <div class="card-header">{{ __('Scheduled patrols') }}</div>
                <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        <thead><tr><th>{{ __('Route') }}</th><th>{{ __('Guard') }}</th><th>{{ __('Scheduled') }}</th><th>{{ __('Scans') }}</th><th>{{ __('Status') }}</th><th></th></tr></thead>
                        <tbody>
                            @forelse ($patrols as $patrol)
                                <tr wire:key="patrol-{{ $patrol->id }}">
                                    <td>{{ $patrol->route->code }}</td>
                                    <td>{{ $patrol->guardStaff->fullName() }}</td>
                                    <td>{{ $patrol->scheduled_at->format('d M H:i') }}</td>
                                    <td>{{ $patrol->checkpoints_scanned }}/{{ $patrol->checkpoints_expected }}</td>
                                    <td>
                                        <span class="{{ in_array($patrol->status, ['missed', 'incomplete'], true) ? 'badge bg-danger' : '' }}">{{ $patrol->status }}</span>
                                    </td>
                                    <td class="d-flex gap-1">
                                        @if (in_array($patrol->status, ['scheduled', 'in_progress'], true))
                                            <button type="button" class="btn btn-outline-secondary btn-sm" wire:click="selectForScan({{ $patrol->id }})">{{ __('Scan') }}</button>
                                            <button type="button" class="btn btn-outline-success btn-sm" wire:click="complete({{ $patrol->id }})">{{ __('Complete') }}</button>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="6" class="text-center text-body-secondary py-3">{{ __('No scheduled patrols.') }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <div class="col-lg-5">
            <div class="card mb-3">
                <div class="card-header">{{ __('New route') }}</div>
                <div class="card-body">
                    <input type="text" class="form-control mb-2" wire:model="routeCode" placeholder="{{ __('Code') }}">
                    <input type="text" class="form-control mb-2" wire:model="routeName" placeholder="{{ __('Name') }}">
                    <select class="form-select mb-2" wire:model="checkpointIds" multiple>
                        @foreach ($checkpoints as $cp)
                            <option value="{{ $cp->id }}">{{ $cp->code }} — {{ $cp->name }}</option>
                        @endforeach
                    </select>
                    <select class="form-select mb-2" wire:model="frequency">
                        @foreach (['hourly', 'two_hourly', 'shift_start', 'random'] as $freq)
                            <option value="{{ $freq }}">{{ $freq }}</option>
                        @endforeach
                    </select>
                    <button type="button" class="btn btn-primary btn-sm" wire:click="createRoute">{{ __('Create route') }}</button>
                </div>
            </div>
            <div class="card mb-3">
                <div class="card-header">{{ __('Schedule patrol') }}</div>
                <div class="card-body">
                    <select class="form-select mb-2" wire:model="patrolRouteId">
                        <option value="">{{ __('Route') }}</option>
                        @foreach ($routes as $route)
                            <option value="{{ $route->id }}">{{ $route->code }}</option>
                        @endforeach
                    </select>
                    <select class="form-select mb-2" wire:model="guardStaffId">
                        <option value="">{{ __('Guard') }}</option>
                        @foreach ($staff as $member)
                            <option value="{{ $member->id }}">{{ $member->fullName() }}</option>
                        @endforeach
                    </select>
                    <button type="button" class="btn btn-primary btn-sm" wire:click="schedule">{{ __('Schedule now') }}</button>
                </div>
            </div>

            @if ($scanningPatrolId !== null)
                <div class="card">
                    <div class="card-header">{{ __('Scan checkpoint for patrol #') }}{{ $scanningPatrolId }}</div>
                    <div class="card-body">
                        <select class="form-select mb-2" wire:model="scanCheckpointId">
                            <option value="">{{ __('Checkpoint') }}</option>
                            @foreach ($checkpoints as $cp)
                                <option value="{{ $cp->id }}">{{ $cp->code }}</option>
                            @endforeach
                        </select>
                        <select class="form-select mb-2" wire:model="scanMethod">
                            <option value="qr">{{ __('QR') }}</option>
                            <option value="nfc">{{ __('NFC') }}</option>
                            <option value="manual">{{ __('Manual') }}</option>
                        </select>
                        <button type="button" class="btn btn-primary btn-sm" wire:click="scan">{{ __('Record scan') }}</button>
                    </div>
                </div>
            @endif
        </div>
    </div>
</div>
