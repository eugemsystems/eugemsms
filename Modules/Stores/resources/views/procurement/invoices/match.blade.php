<div>
    <h4 class="mb-1">{{ __('Match review') }}</h4>
    <p class="text-body-secondary mb-4">{{ __('Every variance shown, line by line — approving is a decision, not a click through a dialog.') }}</p>

    <div class="card">
        <div class="card-header">
            <select class="form-select form-select-sm" style="max-width: 24rem" wire:model="grnAccrualAccountId">
                <option value="">{{ __('GRN accrual account') }}</option>
                @foreach ($accounts as $account)
                    <option value="{{ $account->id }}">{{ $account->code }} — {{ $account->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="table-responsive">
            <table class="table table-sm mb-0">
                <thead><tr><th>{{ __('Invoice') }}</th><th>{{ __('Supplier') }}</th><th>{{ __('Total') }}</th><th>{{ __('Match') }}</th><th>{{ __('Variance') }}</th><th>{{ __('Fiscal') }}</th><th>{{ __('Withholding') }}</th><th></th></tr></thead>
                <tbody>
                    @forelse ($invoices as $invoice)
                        <tr wire:key="inv-{{ $invoice->id }}">
                            <td>{{ $invoice->invoice_number }}</td>
                            <td>{{ $invoice->supplier->name }}</td>
                            <td>{{ number_format($invoice->total_minor / 100, 2) }} {{ $invoice->currency }}</td>
                            <td>
                                <span class="badge text-bg-{{ $invoice->match_status === 'matched' ? 'success' : ($invoice->match_status === 'unmatched' ? 'danger' : 'warning') }}">{{ $invoice->match_status }}</span>
                            </td>
                            <td>{{ $invoice->match_variance_minor !== null ? number_format($invoice->match_variance_minor / 100, 2) : '—' }}</td>
                            <td>{{ $invoice->is_fiscal_invoice ? '✓' : '—' }}</td>
                            <td>{{ $invoice->withholding_applied ? number_format($invoice->withholding_minor / 100, 2) : '—' }}</td>
                            <td>
                                <button type="button" class="btn btn-sm btn-primary" wire:click="approve({{ $invoice->id }})" {{ $invoice->match_status === 'unmatched' ? 'disabled' : '' }}>{{ __('Approve') }}</button>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="text-center text-body-secondary py-3">{{ __('Nothing to review.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
