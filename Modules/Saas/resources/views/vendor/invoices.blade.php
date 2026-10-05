<div>
    <div class="mb-4"><h4 class="mb-0">{{ __('Tenant billing') }}</h4><p class="text-body-secondary small mb-0">{{ __('The vendor’s own ledger of what each tenant owes and has paid.') }}</p></div>
    <div class="row g-4">
        <div class="col-xl-8">
            <div class="card">
                <div class="card-header"><select class="form-select form-select-sm w-auto" wire:model.live="statusFilter"><option value="">{{ __('All statuses') }}</option>@foreach (['issued', 'paid', 'void'] as $value) <option value="{{ $value }}">{{ __(ucfirst($value)) }}</option> @endforeach</select></div>
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0">
                        <thead><tr><th>{{ __('Invoice') }}</th><th>{{ __('Tenant') }}</th><th>{{ __('Due') }}</th><th class="text-end">{{ __('Total') }}</th><th class="text-end">{{ __('Paid') }}</th><th>{{ __('Status') }}</th><th></th></tr></thead>
                        <tbody>
                            @forelse ($invoices as $invoice)
                                <tr wire:key="inv-{{ $invoice->id }}">
                                    <td>{{ $invoice->invoice_number }}</td><td>{{ $invoice->tenant?->name }}</td><td class="small">{{ $invoice->due_date?->toFormattedDateString() }}</td>
                                    <td class="text-end">{{ number_format($invoice->total_minor / 100, 2) }} {{ $invoice->currency }}</td>
                                    <td class="text-end">{{ number_format($invoice->amountPaidMinor() / 100, 2) }}</td>
                                    <td>{{ __(ucfirst($invoice->status)) }}</td>
                                    <td class="text-end">@unless (in_array($invoice->status, ['paid', 'void'])) <button type="button" class="btn btn-sm btn-outline-primary" wire:click="startPayment({{ $invoice->id }})">{{ __('Record payment') }}</button> @endunless</td>
                                </tr>
                                @if ($payingInvoiceId === $invoice->id)
                                    <tr wire:key="pay-{{ $invoice->id }}"><td colspan="7">
                                        <div class="row g-2 align-items-start">
                                            <div class="col-md-3"><input type="number" step="0.01" min="0" class="form-control form-control-sm" wire:model="amount">@error('amount') <div class="text-danger small">{{ $message }}</div> @enderror</div>
                                            <div class="col-md-3"><select class="form-select form-select-sm" wire:model="method">@foreach (['bank_transfer', 'cash', 'card', 'mobile_money', 'gateway'] as $m) <option value="{{ $m }}">{{ str_replace('_', ' ', $m) }}</option> @endforeach</select></div>
                                            <div class="col-md-3"><input type="text" class="form-control form-control-sm" wire:model="reference" placeholder="{{ __('Reference') }}"></div>
                                            <div class="col-md-3"><button type="button" class="btn btn-primary btn-sm" wire:click="recordPayment">{{ __('Save') }}</button></div>
                                        </div>
                                    </td></tr>
                                @endif
                            @empty
                                <tr><td colspan="7" class="text-center text-body-secondary py-3">{{ __('No invoices.') }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <div class="col-xl-4">
            <div class="card"><div class="card-header">{{ __('Issue an invoice') }}</div><div class="card-body">
                <select class="form-select form-select-sm mb-2" wire:model="subscriptionId"><option value="">{{ __('Subscription…') }}</option>@foreach ($subscriptions as $subscription) <option value="{{ $subscription->id }}">{{ $subscription->tenant?->name }} · {{ $subscription->status }}</option> @endforeach</select>
                @error('subscriptionId') <div class="text-danger small mb-2">{{ $message }}</div> @enderror
                <input type="month" class="form-control form-control-sm mb-2" wire:model="periodMonth">
                @error('periodMonth') <div class="text-danger small mb-2">{{ $message }}</div> @enderror
                <button type="button" class="btn btn-primary btn-sm" wire:click="issue">{{ __('Issue') }}</button>
            </div></div>
        </div>
    </div>
</div>
