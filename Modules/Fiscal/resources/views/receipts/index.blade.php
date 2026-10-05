<div>
    <h4 class="mb-1">{{ __('Fiscal receipts') }}</h4>

    <select class="form-select mb-3" style="max-width:16rem" wire:model.live="statusFilter">
        <option value="">{{ __('All statuses') }}</option>
        <option value="queued">{{ __('Queued') }}</option>
        <option value="offline_queued">{{ __('Offline queued') }}</option>
        <option value="accepted">{{ __('Accepted') }}</option>
        <option value="rejected">{{ __('Rejected') }}</option>
        <option value="failed">{{ __('Failed') }}</option>
    </select>

    <div class="card">
        <div class="table-responsive">
            <table class="table table-sm mb-0">
                <thead><tr><th>{{ __('Invoice #') }}</th><th>{{ __('Counter') }}</th><th>{{ __('Currency') }}</th><th>{{ __('Total') }}</th><th>{{ __('Status') }}</th><th>{{ __('Verification code') }}</th></tr></thead>
                <tbody>
                    @forelse ($receipts as $receipt)
                        <tr wire:key="receipt-{{ $receipt->id }}">
                            <td>{{ $receipt->invoice_number }}</td>
                            <td>{{ $receipt->receipt_counter }} / {{ $receipt->global_counter }}</td>
                            <td>{{ $receipt->receipt_currency }}</td>
                            <td>{{ number_format($receipt->total_minor / 100, 2) }}</td>
                            <td><span class="badge {{ $receipt->status === 'accepted' ? 'bg-success' : ($receipt->status === 'rejected' || $receipt->status === 'failed' ? 'bg-danger' : 'bg-secondary') }}">{{ $receipt->status }}</span></td>
                            <td class="small">{{ $receipt->verification_code ?? '—' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="text-center text-body-secondary py-3">{{ __('No fiscal receipts yet.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
