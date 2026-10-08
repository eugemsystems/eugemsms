<div>
    <div class="mb-4">
        <h4 class="mb-0">{{ __('API usage (platform)') }}</h4>
        <p class="text-body-secondary small mb-0">{{ __('Aggregate third-party API usage across every tenant — request volume, errors and latency.') }}</p>
    </div>
    <div class="mb-3">
        <select class="form-select form-select-sm w-auto" wire:model.live="days">
            <option value="1">{{ __('Last 24 hours') }}</option><option value="7">{{ __('Last 7 days') }}</option><option value="30">{{ __('Last 30 days') }}</option>
        </select>
    </div>

    <div class="row g-3 mb-3">
        <div class="col-md-4"><div class="card"><div class="card-body"><div class="text-body-secondary small">{{ __('Total requests') }}</div><div class="fs-4">{{ number_format((int) ($totals->requests ?? 0)) }}</div></div></div></div>
        <div class="col-md-4"><div class="card"><div class="card-body"><div class="text-body-secondary small">{{ __('Total errors') }}</div><div class="fs-4">{{ number_format((int) ($totals->errors ?? 0)) }}</div></div></div></div>
        <div class="col-md-4"><div class="card"><div class="card-body"><div class="text-body-secondary small">{{ __('Avg latency (ms)') }}</div><div class="fs-4">{{ ($totals->avg_ms ?? null) !== null ? number_format((float) $totals->avg_ms) : '—' }}</div></div></div></div>
    </div>

    <div class="card">
        <div class="card-header">{{ __('By tenant') }}</div>
        <div class="table-responsive">
            <table class="table table-sm mb-0">
                <thead><tr><th>{{ __('Tenant') }}</th><th>{{ __('School') }}</th><th class="text-end">{{ __('Requests') }}</th><th class="text-end">{{ __('Errors') }}</th><th class="text-end">{{ __('Avg ms') }}</th></tr></thead>
                <tbody>
                    @forelse ($byTenant as $row)
                        <tr wire:key="u-{{ $row->school_id }}">
                            <td>{{ $schools[$row->school_id]->tenant?->name ?? '—' }}</td>
                            <td>{{ $schools[$row->school_id]->name ?? '—' }}</td>
                            <td class="text-end">{{ number_format((int) $row->requests) }}</td>
                            <td class="text-end">{{ number_format((int) $row->errors) }}</td>
                            <td class="text-end">{{ $row->avg_ms !== null ? number_format((float) $row->avg_ms) : '—' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="text-center text-body-secondary py-3">{{ __('No API calls in this period.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
