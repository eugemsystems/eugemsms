<div>
    <h4 class="mb-1">{{ __('Consolidated incident register') }}</h4>
    <p class="text-body-secondary small">{{ __('Health, discipline, security, transport and data-protection incidents, for board reporting. Safeguarding is always excluded.') }}</p>

    <div class="row g-2 align-items-end mb-3">
        <div class="col-auto">
            <label class="form-label small mb-0">{{ __('From') }}</label>
            <input type="date" class="form-control" wire:model="periodStart">
        </div>
        <div class="col-auto">
            <label class="form-label small mb-0">{{ __('To') }}</label>
            <input type="date" class="form-control" wire:model="periodEnd">
        </div>
        <div class="col-auto">
            <button type="button" class="btn btn-primary btn-sm" wire:click="generate">{{ __('Generate') }}</button>
        </div>
    </div>

    <div class="card">
        <div class="table-responsive">
            <table class="table table-sm mb-0">
                <thead><tr><th>{{ __('Source') }}</th><th>{{ __('Occurred') }}</th><th>{{ __('Type') }}</th><th>{{ __('Description') }}</th><th>{{ __('Severity') }}</th></tr></thead>
                <tbody>
                    @forelse ($entries as $entry)
                        <tr>
                            <td>{{ $entry['source'] }}</td>
                            <td>{{ $entry['occurredAt']->toDateString() }}</td>
                            <td>{{ $entry['type'] }}</td>
                            <td>{{ $entry['description'] }}</td>
                            <td>{{ $entry['severity'] ?? '—' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="text-center text-body-secondary py-3">{{ __('No incidents in this period.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
