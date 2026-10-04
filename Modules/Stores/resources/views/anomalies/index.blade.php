<div>
    <h4 class="mb-1">{{ __('Consumption anomalies') }}</h4>
    <p class="text-body-secondary mb-4">⭐ {{ __('Never auto-dismissed — an investigation note is always required.') }}</p>

    <div class="card mb-4">
        <div class="card-header">{{ __('Compute baseline / detect') }}</div>
        <div class="card-body">
            <div class="row g-2 mb-2">
                <div class="col-md-3">
                    <select class="form-select" wire:model="storeId">
                        <option value="">{{ __('Store') }}</option>
                        @foreach ($stores as $store)
                            <option value="{{ $store->id }}">{{ $store->code }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <select class="form-select" wire:model="itemId">
                        <option value="">{{ __('Item') }}</option>
                        @foreach ($items as $item)
                            <option value="{{ $item->id }}">{{ $item->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <select class="form-select" wire:model="periodType">
                        <option value="daily">{{ __('Daily') }}</option>
                        <option value="weekly">{{ __('Weekly') }}</option>
                        <option value="per_boarder_day">{{ __('Per boarder-day') }}</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <input type="number" class="form-control" wire:model="baselineDays" placeholder="{{ __('Baseline days') }}">
                </div>
                <div class="col-md-2">
                    <button type="button" class="btn btn-outline-secondary w-100" wire:click="computeBaseline">{{ __('Compute baseline') }}</button>
                </div>
            </div>
            <div class="row g-2">
                <div class="col-md-3"><input type="date" class="form-control" wire:model="periodStart"></div>
                <div class="col-md-3"><input type="date" class="form-control" wire:model="periodEnd"></div>
                <div class="col-md-2"><button type="button" class="btn btn-primary w-100" wire:click="detect">{{ __('Detect') }}</button></div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header">{{ __('Flagged') }}</div>
        <div class="table-responsive">
            <table class="table table-sm mb-0">
                <thead><tr><th>{{ __('Item') }}</th><th>{{ __('Store') }}</th><th>{{ __('Period') }}</th><th>{{ __('Variance') }}</th><th>{{ __('Severity') }}</th><th>{{ __('Occupancy') }}</th><th>{{ __('Note') }}</th><th></th></tr></thead>
                <tbody>
                    @forelse ($anomalies as $anomaly)
                        <tr wire:key="anomaly-{{ $anomaly->id }}">
                            <td>{{ $anomaly->item->name }}</td>
                            <td>{{ $anomaly->store->code }}</td>
                            <td>{{ $anomaly->period_start->format('Y-m-d') }} – {{ $anomaly->period_end->format('Y-m-d') }}</td>
                            <td>{{ $anomaly->variance_percent }}%</td>
                            <td><span class="badge text-bg-{{ $anomaly->severity === 'high' ? 'danger' : 'warning' }}">{{ $anomaly->severity }}</span></td>
                            <td>{{ $anomaly->occupancy_factor ?? '—' }}</td>
                            <td><input type="text" class="form-control form-control-sm" wire:model="notes.{{ $anomaly->id }}" placeholder="{{ __('Investigation note') }}"></td>
                            <td class="d-flex gap-1">
                                <button type="button" class="btn btn-sm btn-success" wire:click="investigate({{ $anomaly->id }}, true)">{{ __('Explained') }}</button>
                                <button type="button" class="btn btn-sm btn-outline-danger" wire:click="investigate({{ $anomaly->id }}, false)">{{ __('Unexplained') }}</button>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="text-center text-body-secondary py-3">{{ __('No open anomalies.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
