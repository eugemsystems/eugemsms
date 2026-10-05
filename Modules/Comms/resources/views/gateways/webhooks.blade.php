<div>
    <div class="d-flex align-items-center gap-2 mb-4">
        <a href="{{ route('comms.gateways.index', $school) }}" class="btn btn-icon btn-outline-secondary btn-sm" wire:navigate>
            <i class="ri ri-arrow-left-line"></i>
        </a>
        <div>
            <h4 class="mb-0">{{ __('Webhook log') }}</h4>
            <p class="text-body-secondary small mb-0">{{ __('Receipt metadata only — raw payloads are never displayed. Showing the latest 100.') }}</p>
        </div>
    </div>

    <select class="form-select form-select-sm mb-3 w-auto" wire:model.live="statusFilter">
        <option value="">{{ __('All outcomes') }}</option>
        <option value="processed">{{ __('Processed') }}</option>
        <option value="failed">{{ __('Failed') }}</option>
        <option value="received">{{ __('Received') }}</option>
        <option value="ignored">{{ __('Ignored') }}</option>
    </select>

    <div class="card">
        <div class="table-responsive">
            <table class="table table-sm mb-0">
                <thead><tr><th>{{ __('Received') }}</th><th>{{ __('Driver') }}</th><th>{{ __('Event') }}</th><th>{{ __('Signature') }}</th><th>{{ __('Outcome') }}</th></tr></thead>
                <tbody>
                    @forelse ($webhooks as $webhook)
                        <tr wire:key="wh-{{ $webhook->id }}">
                            <td class="small">{{ $webhook->received_at?->toDateTimeString() }}</td>
                            <td>{{ $webhook->driver }}</td>
                            <td>{{ $webhook->event_type ?? '—' }}</td>
                            <td><span class="badge {{ $webhook->signature_valid ? 'bg-label-success' : 'bg-label-danger' }}">{{ $webhook->signature_valid ? __('valid') : __('invalid') }}</span></td>
                            <td>{{ $webhook->processing_status }} @if ($webhook->processing_error) <span class="text-danger small">— {{ $webhook->processing_error }}</span> @endif</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="text-center text-body-secondary py-3">{{ __('No webhooks received.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
