<div>
    <h4 class="mb-1">{{ __('Offline fiscal queue') }}</h4>
    <p class="text-body-secondary small">{{ __('Drains automatically on reconnect in counter order. Shown here for visibility and manual drain.') }}</p>

    <div class="row g-2 mb-3">
        <div class="col-auto"><div class="card p-3">{{ __('Depth') }}: <strong>{{ $depth }}</strong></div></div>
        <div class="col-auto"><div class="card p-3">{{ __('Oldest') }}: <strong>{{ $oldest?->invoice_number ?? '—' }}</strong></div></div>
    </div>

    <button type="button" class="btn btn-primary btn-sm mb-3" wire:click="drain">{{ __('Drain queue now') }}</button>

    <div class="card">
        <div class="table-responsive">
            <table class="table table-sm mb-0">
                <thead><tr><th>{{ __('Global #') }}</th><th>{{ __('Invoice #') }}</th><th>{{ __('Attempts') }}</th><th>{{ __('Last attempt') }}</th></tr></thead>
                <tbody>
                    @forelse ($queued as $receipt)
                        <tr wire:key="queued-{{ $receipt->id }}">
                            <td>{{ $receipt->global_counter }}</td>
                            <td>{{ $receipt->invoice_number }}</td>
                            <td>{{ $receipt->attempt_count }}</td>
                            <td>{{ $receipt->last_attempt_at?->diffForHumans() ?? '—' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="text-center text-body-secondary py-3">{{ __('Queue is empty.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
