<div>
    <h4 class="mb-1">🇿🇼 {{ __('Register supplier invoice') }}</h4>
    <p class="text-body-secondary mb-4">{{ __('Fiscal status and withholding are both assessed here, once, and stored.') }}</p>

    @if ($unclaimableVatAdvisoryMinor > 0)
        <div class="alert alert-warning">
            ⚠ {{ __('Input VAT of :amount is not claimable on this invoice. Request a fiscal tax invoice from the supplier.', ['amount' => number_format($unclaimableVatAdvisoryMinor / 100, 2)]) }}
        </div>
    @endif

    <div class="card" style="max-width: 50rem">
        <div class="card-body">
            <div class="row g-2 mb-2">
                <div class="col-md-6">
                    <select class="form-select" wire:model.live="supplierId">
                        <option value="">{{ __('Supplier') }}</option>
                        @foreach ($suppliers as $supplier)
                            <option value="{{ $supplier->id }}">{{ $supplier->name }} @if ($supplier->is_vat_registered) ({{ __('VAT') }}) @endif</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-6">
                    <select class="form-select" wire:model="purchaseOrderId">
                        <option value="">{{ __('Purchase order (optional)') }}</option>
                        @foreach ($orders as $order)
                            <option value="{{ $order->id }}">{{ $order->po_number }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
            <div class="row g-2 mb-2">
                <div class="col-md-4"><input type="text" class="form-control" wire:model="invoiceNumber" placeholder="{{ __('Invoice number') }}"></div>
                <div class="col-md-4"><input type="date" class="form-control" wire:model="invoiceDate" placeholder="{{ __('Invoice date') }}"></div>
                <div class="col-md-4"><input type="date" class="form-control" wire:model="dueDate" placeholder="{{ __('Due date') }}"></div>
            </div>
            <div class="form-check mb-2">
                <input type="checkbox" class="form-check-input" id="isFiscalInvoice" wire:model.live="isFiscalInvoice">
                <label class="form-check-label" for="isFiscalInvoice">🇿🇼 {{ __('This is a fiscal tax invoice (FDMS-connected device)') }}</label>
            </div>
            @if ($isFiscalInvoice)
                <div class="row g-2 mb-2">
                    <div class="col-md-6"><input type="text" class="form-control" wire:model="fiscalDeviceId" placeholder="{{ __('Fiscal device ID') }}"></div>
                    <div class="col-md-6"><input type="text" class="form-control" wire:model="fiscalVerificationCode" placeholder="{{ __('Verification code') }}"></div>
                </div>
            @endif

            <table class="table table-sm">
                <thead><tr><th>{{ __('Description') }}</th><th>{{ __('Qty') }}</th><th>{{ __('Unit price') }}</th><th>{{ __('Tax cat.') }}</th><th>{{ __('Tax %') }}</th><th>{{ __('Expense a/c') }}</th><th>{{ __('Cost centre') }}</th><th></th></tr></thead>
                <tbody>
                    @foreach ($lines as $index => $line)
                        <tr wire:key="inv-line-{{ $index }}">
                            <td><input type="text" class="form-control form-control-sm" wire:model="lines.{{ $index }}.description"></td>
                            <td><input type="number" step="0.0001" class="form-control form-control-sm" style="width: 5rem" wire:model="lines.{{ $index }}.quantity"></td>
                            <td><input type="number" class="form-control form-control-sm" style="width: 6rem" wire:model="lines.{{ $index }}.unit_price_minor"></td>
                            <td>
                                <select class="form-select form-select-sm" wire:model.live="lines.{{ $index }}.tax_category">
                                    <option value="standard">{{ __('Standard') }}</option>
                                    <option value="zero">{{ __('Zero') }}</option>
                                    <option value="exempt">{{ __('Exempt') }}</option>
                                </select>
                            </td>
                            <td><input type="number" step="0.01" class="form-control form-control-sm" style="width: 5rem" wire:model.live="lines.{{ $index }}.tax_rate_percent"></td>
                            <td>
                                <select class="form-select form-select-sm" wire:model="lines.{{ $index }}.expense_account_id">
                                    <option value="">—</option>
                                    @foreach ($accounts as $account)
                                        <option value="{{ $account->id }}">{{ $account->code }}</option>
                                    @endforeach
                                </select>
                            </td>
                            <td>
                                <select class="form-select form-select-sm" wire:model="lines.{{ $index }}.cost_centre_id">
                                    <option value="">—</option>
                                    @foreach ($costCentres as $cc)
                                        <option value="{{ $cc->id }}">{{ $cc->code }}</option>
                                    @endforeach
                                </select>
                            </td>
                            <td>
                                @if (count($lines) > 1)
                                    <button type="button" class="btn btn-icon btn-sm btn-outline-danger" wire:click="removeLine({{ $index }})"><i class="ri ri-delete-bin-line"></i></button>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            <button type="button" class="btn btn-sm btn-outline-secondary mb-3" wire:click="addLine">{{ __('Add line') }}</button>
            <div><button type="button" class="btn btn-primary" wire:click="register">{{ __('Register invoice') }}</button></div>
        </div>
    </div>
</div>
