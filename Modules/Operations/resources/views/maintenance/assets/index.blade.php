<div>
    <h4 class="mb-1">{{ __('Maintenance assets') }}</h4>
    <p class="text-body-secondary mb-4">{{ __('Buildings, generators, boreholes, pumps and other maintainable things — wider than the FIN-10 fixed asset register.') }}</p>

    <div class="row g-4">
        <div class="col-lg-7">
            <div class="card">
                <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        <thead><tr><th>{{ __('Code') }}</th><th>{{ __('Name') }}</th><th>{{ __('Type') }}</th><th>{{ __('Criticality') }}</th><th>{{ __('Next service') }}</th><th></th></tr></thead>
                        <tbody>
                            @forelse ($assets as $asset)
                                <tr wire:key="asset-{{ $asset->id }}" class="{{ $asset->isServiceOverdue() ? 'table-danger' : '' }}">
                                    <td>{{ $asset->code }}</td>
                                    <td>{{ $asset->name }}</td>
                                    <td>{{ $asset->asset_type }}</td>
                                    <td>
                                        @if ($asset->criticality === 'critical') <span class="badge text-bg-danger">⭐ {{ __('critical') }}</span>
                                        @else {{ $asset->criticality }} @endif
                                    </td>
                                    <td>{{ $asset->next_service_due_on?->toFormattedDateString() ?? '—' }}</td>
                                    <td><button type="button" class="btn btn-sm btn-outline-secondary" wire:click="selectAsset({{ $asset->id }})">{{ __('History') }}</button></td>
                                </tr>
                            @empty
                                <tr><td colspan="6" class="text-center text-body-secondary py-3">{{ __('No maintenance assets.') }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            @if ($selected)
                <div class="card mt-3">
                    <div class="card-header">{{ __('History — :name', ['name' => $selected->name]) }}</div>
                    <div class="card-body">
                        <h6>{{ __('Fault reports') }}</h6>
                        <ul class="list-unstyled small">
                            @forelse ($selected->faultReports as $report)
                                <li>{{ $report->report_number }} — {{ $report->status }}</li>
                            @empty
                                <li class="text-body-secondary">{{ __('None.') }}</li>
                            @endforelse
                        </ul>
                        <h6>{{ __('Work orders') }}</h6>
                        <ul class="list-unstyled small">
                            @forelse ($selected->workOrders as $wo)
                                <li>{{ $wo->work_order_number }} — {{ $wo->status }} — {{ number_format($wo->total_cost_minor / 100, 2) }} {{ $wo->currency }}</li>
                            @empty
                                <li class="text-body-secondary">{{ __('None.') }}</li>
                            @endforelse
                        </ul>
                    </div>
                </div>
            @endif
        </div>
        <div class="col-lg-5">
            <div class="card">
                <div class="card-header">{{ __('New maintenance asset') }}</div>
                <div class="card-body">
                    <input type="text" class="form-control mb-2" wire:model="code" placeholder="{{ __('Code') }}">
                    <input type="text" class="form-control mb-2" wire:model="name" placeholder="{{ __('Name') }}">
                    <select class="form-select mb-2" wire:model="assetType">
                        @foreach (['building', 'vehicle', 'generator', 'borehole', 'pump', 'solar', 'kitchen_equip', 'ict', 'furniture', 'grounds', 'plumbing', 'electrical', 'farm_equipment'] as $type)
                            <option value="{{ $type }}">{{ $type }}</option>
                        @endforeach
                    </select>
                    <select class="form-select mb-2" wire:model="costCentreId">
                        <option value="">{{ __('Cost centre') }}</option>
                        @foreach ($costCentres as $cc)
                            <option value="{{ $cc->id }}">{{ $cc->code }} — {{ $cc->name }}</option>
                        @endforeach
                    </select>
                    <input type="text" class="form-control mb-2" wire:model="location" placeholder="{{ __('Location (optional)') }}">
                    <input type="text" class="form-control mb-2" wire:model="building" placeholder="{{ __('Building (optional)') }}">
                    <select class="form-select mb-2" wire:model="criticality">
                        @foreach (['critical', 'high', 'normal', 'low'] as $level)
                            <option value="{{ $level }}">{{ $level }}</option>
                        @endforeach
                    </select>
                    <input type="number" class="form-control mb-2" wire:model="serviceIntervalDays" placeholder="{{ __('Service interval (days, optional)') }}">
                    <button type="button" class="btn btn-primary btn-sm" wire:click="create">{{ __('Create asset') }}</button>
                </div>
            </div>
        </div>
    </div>
</div>
