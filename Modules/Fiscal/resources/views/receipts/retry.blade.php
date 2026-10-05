<div>
    <h4 class="mb-1">{{ __('Fiscal retry workbench') }} ⭐</h4>
    <p class="text-body-secondary small">{{ __('Failed and rejected receipts, with the full retained error. Nothing here is ever silently discarded.') }}</p>

    <div class="card mb-4">
        <div class="card-header">{{ __('Needs attention') }}</div>
        <div class="table-responsive">
            <table class="table table-sm mb-0">
                <thead><tr><th>{{ __('Invoice #') }}</th><th>{{ __('Status') }}</th><th>{{ __('Error') }}</th><th>{{ __('Attempts') }}</th><th></th></tr></thead>
                <tbody>
                    @forelse ($failedReceipts as $receipt)
                        <tr wire:key="failed-{{ $receipt->id }}">
                            <td>{{ $receipt->invoice_number }}</td>
                            <td><span class="badge bg-danger">{{ $receipt->status }}</span></td>
                            <td class="small">{{ $receipt->error_message ?? '—' }}</td>
                            <td>{{ $receipt->attempt_count }}</td>
                            <td><button type="button" class="btn btn-outline-primary btn-sm" wire:click="retry({{ $receipt->id }})">{{ __('Retry') }}</button></td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="text-center text-body-secondary py-3">{{ __('Nothing needs attention.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="card">
        <div class="card-header">{{ __('Raise a credit note') }}</div>
        <div class="table-responsive">
            <table class="table table-sm mb-0">
                <thead><tr><th>{{ __('Invoice #') }}</th><th>{{ __('Total') }}</th><th></th></tr></thead>
                <tbody>
                    @forelse ($acceptedReceipts as $receipt)
                        <tr wire:key="accepted-{{ $receipt->id }}">
                            <td>{{ $receipt->invoice_number }}</td>
                            <td>{{ number_format($receipt->total_minor / 100, 2) }} {{ $receipt->receipt_currency }}</td>
                            <td><button type="button" class="btn btn-outline-warning btn-sm" wire:click="startCreditNote({{ $receipt->id }})">{{ __('Credit note') }}</button></td>
                        </tr>
                        @if ($creditNoteReceiptId === $receipt->id)
                            <tr>
                                <td colspan="3">
                                    <input type="text" class="form-control form-control-sm d-inline-block mb-0" style="width:auto" wire:model="creditReason" placeholder="{{ __('Credit reason') }}">
                                    <button type="button" class="btn btn-sm btn-warning" wire:click="raiseCreditNote">{{ __('Raise') }}</button>
                                </td>
                            </tr>
                        @endif
                    @empty
                        <tr><td colspan="3" class="text-center text-body-secondary py-3">{{ __('No accepted receipts.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
