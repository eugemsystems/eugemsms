<div>
    <h4 class="mb-1">{{ __('Payment run') }}</h4>
    <p class="text-body-secondary mb-4">{{ __('Settles the full balance of every selected invoice in one run. The approver must differ from every invoice\'s own approver.') }}</p>

    <div class="card" style="max-width: 44rem">
        <div class="card-body">
            <select class="form-select mb-2" wire:model.live="supplierId">
                <option value="">{{ __('Supplier') }}</option>
                @foreach ($suppliers as $supplier)
                    <option value="{{ $supplier->id }}">{{ $supplier->name }}</option>
                @endforeach
            </select>

            @if ($dueInvoices->isNotEmpty())
                <div class="border rounded p-2 mb-2">
                    @foreach ($dueInvoices as $invoice)
                        <div class="form-check">
                            <input type="checkbox" class="form-check-input" wire:model="invoiceIds" value="{{ $invoice->id }}" id="inv-{{ $invoice->id }}">
                            <label class="form-check-label" for="inv-{{ $invoice->id }}">
                                {{ $invoice->invoice_number }} — {{ number_format($invoice->balance_minor / 100, 2) }} {{ $invoice->currency }}
                                @if ($invoice->withholding_applied) <span class="badge text-bg-warning">{{ __('withholding') }} {{ number_format($invoice->withholding_minor / 100, 2) }}</span> @endif
                            </label>
                        </div>
                    @endforeach
                </div>
            @endif

            <div class="row g-2 mb-2">
                <div class="col-md-4"><input type="date" class="form-control" wire:model="paymentDate"></div>
                <div class="col-md-4">
                    <select class="form-select" wire:model="paymentMethod">
                        <option value="bank_transfer">{{ __('Bank transfer') }}</option>
                        <option value="cheque">{{ __('Cheque') }}</option>
                        <option value="cash">{{ __('Cash') }}</option>
                        <option value="mobile_money">{{ __('Mobile money') }}</option>
                        <option value="rtgs">{{ __('RTGS') }}</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <select class="form-select" wire:model="bankAccountId">
                        <option value="">{{ __('Bank account') }}</option>
                        @foreach ($bankAccounts as $account)
                            <option value="{{ $account->id }}">{{ $account->bank_name }} — {{ $account->account_name }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
            <select class="form-select mb-2" wire:model="withholdingPayableAccountId">
                <option value="">{{ __('Withholding tax payable account (required if any invoice withholds)') }}</option>
                @foreach ($accounts as $account)
                    <option value="{{ $account->id }}">{{ $account->code }} — {{ $account->name }}</option>
                @endforeach
            </select>
            <input type="text" class="form-control mb-2" wire:model="reference" placeholder="{{ __('Reference (optional)') }}">
            <button type="button" class="btn btn-primary" wire:click="pay">{{ __('Record payment') }}</button>
        </div>
    </div>
</div>
