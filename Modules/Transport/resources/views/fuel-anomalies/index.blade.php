<div>
    <h4 class="mb-1">{{ __('Fuel anomalies') }} ⭐</h4>
    <p class="text-body-secondary mb-4">{{ __('Never dismissed without a recorded explanation.') }}</p>

    <button type="button" class="btn btn-outline-primary btn-sm mb-3" wire:click="runCumulativeCheck">{{ __('Run 30-day cumulative check now') }}</button>

    <div class="card">
        <div class="table-responsive">
            <table class="table table-sm mb-0">
                <thead><tr><th>{{ __('Vehicle') }}</th><th>{{ __('Date') }}</th><th>{{ __('Variance') }}</th><th>{{ __('Explanation') }}</th><th></th></tr></thead>
                <tbody>
                    @forelse ($anomalies as $log)
                        <tr wire:key="anomaly-{{ $log->id }}">
                            <td>{{ $log->vehicle->fleet_number }}</td>
                            <td>{{ $log->fuelled_at->toFormattedDateString() }}</td>
                            <td>{{ $log->variance_percent !== null ? number_format((float) $log->variance_percent, 1).'%' : '—' }}</td>
                            <td>
                                @if ($log->anomaly_explanation)
                                    <span class="text-body-secondary small">{{ $log->anomaly_explanation }}</span>
                                @else
                                    <input type="text" class="form-control form-control-sm" wire:model="explanation.{{ $log->id }}" placeholder="{{ __('Explanation (required)') }}">
                                @endif
                            </td>
                            <td>
                                @if (! $log->anomaly_explanation)
                                    <button type="button" class="btn btn-sm btn-outline-primary" wire:click="explain({{ $log->id }})">{{ __('Record') }}</button>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="text-center text-body-secondary py-3">{{ __('No anomalies.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
