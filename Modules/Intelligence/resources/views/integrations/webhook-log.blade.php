<div>
    <div class="mb-4">
        <h4 class="mb-0">{{ __('Webhook delivery log') }}</h4>
        <p class="text-body-secondary small mb-0">{{ __('Every delivery attempt, append-only. Payloads are not shown here.') }}</p>
    </div>
    <div class="mb-3">
        <select class="form-select form-select-sm w-auto" wire:model.live="status">
            <option value="">{{ __('All statuses') }}</option>
            @foreach ($statuses as $value) <option value="{{ $value }}">{{ __(ucfirst($value)) }}</option> @endforeach
        </select>
    </div>
    <div class="card">
        <div class="table-responsive">
            <table class="table table-sm mb-0">
                <thead><tr><th>{{ __('Event') }}</th><th>{{ __('Status') }}</th><th class="text-end">{{ __('Attempts') }}</th><th class="text-end">{{ __('Response') }}</th><th>{{ __('Last attempt') }}</th></tr></thead>
                <tbody>
                    @forelse ($deliveries as $delivery)
                        <tr wire:key="d-{{ $delivery->id }}">
                            <td>{{ $delivery->event_name }}</td>
                            <td><span class="badge text-bg-{{ ['delivered' => 'success', 'failed' => 'warning', 'abandoned' => 'danger', 'pending' => 'secondary'][$delivery->status] ?? 'secondary' }}">{{ __(ucfirst($delivery->status)) }}</span></td>
                            <td class="text-end">{{ $delivery->attempt_count }}</td>
                            <td class="text-end">{{ $delivery->response_status ?? '—' }}</td>
                            <td class="small">{{ $delivery->last_attempted_at?->toDayDateTimeString() ?? '—' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="text-center text-body-secondary py-3">{{ __('No deliveries.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
