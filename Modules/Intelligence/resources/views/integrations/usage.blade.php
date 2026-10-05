<div>
    <div class="mb-4">
        <h4 class="mb-0">{{ __('API usage') }}</h4>
        <p class="text-body-secondary small mb-0">{{ __('Request volume, errors and latency for this school’s own clients.') }}</p>
    </div>
    <div class="mb-3">
        <select class="form-select form-select-sm w-auto" wire:model.live="days">
            <option value="1">{{ __('Last 24 hours') }}</option><option value="7">{{ __('Last 7 days') }}</option><option value="30">{{ __('Last 30 days') }}</option>
        </select>
    </div>
    <div class="card">
        <div class="table-responsive">
            <table class="table table-sm mb-0">
                <thead><tr><th>{{ __('Client') }}</th><th class="text-end">{{ __('Requests') }}</th><th class="text-end">{{ __('Errors') }}</th><th class="text-end">{{ __('Avg ms') }}</th></tr></thead>
                <tbody>
                    @forelse ($rows as $row)
                        <tr wire:key="u-{{ $row->client_id }}">
                            <td>{{ $clientNames[$row->client_id] ?? '—' }}</td>
                            <td class="text-end">{{ number_format((int) $row->requests) }}</td>
                            <td class="text-end">{{ number_format((int) $row->errors) }}</td>
                            <td class="text-end">{{ $row->avg_ms !== null ? number_format((float) $row->avg_ms) : '—' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="text-center text-body-secondary py-3">{{ __('No API calls in this period.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
