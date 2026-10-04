<div>
    <div class="d-flex justify-content-between align-items-start mb-4">
        <div>
            <h4 class="mb-1">{{ $supplier->name }} ({{ $supplier->code }})</h4>
            <span class="badge text-bg-{{ $supplier->status === 'active' ? 'success' : 'secondary' }}">{{ $supplier->status }}</span>
        </div>
        <div class="d-flex gap-2">
            <a href="{{ route('stores.procurement.suppliers.clearances', ['school' => $school]) }}" class="btn btn-sm btn-outline-secondary" wire:navigate>🇿🇼 {{ __('Tax clearances') }}</a>
            <a href="{{ route('stores.procurement.suppliers.bank-change', ['school' => $school, 'supplier' => $supplier]) }}" class="btn btn-sm btn-outline-secondary" wire:navigate>{{ __('Change bank details') }}</a>
            <button type="button" class="btn btn-sm btn-outline-primary" wire:click="recalculatePerformance">{{ __('Recalculate performance') }}</button>
        </div>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-md-3"><div class="card card-body"><div class="text-body-secondary small">{{ __('On-time delivery') }}</div><div class="fs-5">{{ $supplier->on_time_delivery_pct !== null ? $supplier->on_time_delivery_pct.'%' : '—' }}</div></div></div>
        <div class="col-md-3"><div class="card card-body"><div class="text-body-secondary small">{{ __('Rejection rate') }}</div><div class="fs-5">{{ $supplier->quality_rejection_pct !== null ? $supplier->quality_rejection_pct.'%' : '—' }}</div></div></div>
        <div class="col-md-3"><div class="card card-body"><div class="text-body-secondary small">{{ __('Rating') }}</div><div class="fs-5">{{ $supplier->rating ?? '—' }}</div></div></div>
        <div class="col-md-3"><div class="card card-body"><div class="text-body-secondary small">{{ __('VAT registered') }}</div><div class="fs-5">{{ $supplier->is_vat_registered ? __('Yes') : __('No') }}</div></div></div>
    </div>

    <div class="row g-4">
        <div class="col-lg-6">
            <div class="card mb-4">
                <div class="card-header">{{ __('Purchase orders') }}</div>
                <div class="table-responsive"><table class="table table-sm mb-0">
                    <thead><tr><th>{{ __('PO') }}</th><th>{{ __('Status') }}</th><th>{{ __('Total') }}</th></tr></thead>
                    <tbody>
                        @forelse ($orders as $order)
                            <tr><td>{{ $order->po_number }}</td><td>{{ $order->status }}</td><td>{{ number_format($order->total_minor / 100, 2) }} {{ $order->currency }}</td></tr>
                        @empty
                            <tr><td colspan="3" class="text-center text-body-secondary py-3">{{ __('None.') }}</td></tr>
                        @endforelse
                    </tbody>
                </table></div>
            </div>
            <div class="card">
                <div class="card-header">{{ __('Payments') }}</div>
                <div class="table-responsive"><table class="table table-sm mb-0">
                    <thead><tr><th>{{ __('Payment') }}</th><th>{{ __('Date') }}</th><th>{{ __('Net') }}</th></tr></thead>
                    <tbody>
                        @forelse ($payments as $payment)
                            <tr><td>{{ $payment->payment_number }}</td><td>{{ $payment->payment_date->format('Y-m-d') }}</td><td>{{ number_format($payment->net_minor / 100, 2) }} {{ $payment->currency }}</td></tr>
                        @empty
                            <tr><td colspan="3" class="text-center text-body-secondary py-3">{{ __('None.') }}</td></tr>
                        @endforelse
                    </tbody>
                </table></div>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="card mb-4">
                <div class="card-header">{{ __('Invoices') }}</div>
                <div class="table-responsive"><table class="table table-sm mb-0">
                    <thead><tr><th>{{ __('Invoice') }}</th><th>{{ __('Status') }}</th><th>{{ __('Balance') }}</th></tr></thead>
                    <tbody>
                        @forelse ($invoices as $invoice)
                            <tr><td>{{ $invoice->invoice_number }}</td><td>{{ $invoice->status }}</td><td>{{ number_format($invoice->balance_minor / 100, 2) }} {{ $invoice->currency }}</td></tr>
                        @empty
                            <tr><td colspan="3" class="text-center text-body-secondary py-3">{{ __('None.') }}</td></tr>
                        @endforelse
                    </tbody>
                </table></div>
            </div>
            <div class="card">
                <div class="card-header">{{ __('Aging') }}</div>
                <div class="table-responsive"><table class="table table-sm mb-0">
                    <thead><tr><th>{{ __('Currency') }}</th><th>{{ __('Current') }}</th><th>1-30</th><th>31-60</th><th>61-90</th><th>90+</th></tr></thead>
                    <tbody>
                        @forelse ($aging as $row)
                            <tr>
                                <td>{{ $row['currency'] }}</td>
                                <td>{{ number_format($row['current'] / 100, 2) }}</td>
                                <td>{{ number_format($row['days_1_30'] / 100, 2) }}</td>
                                <td>{{ number_format($row['days_31_60'] / 100, 2) }}</td>
                                <td>{{ number_format($row['days_61_90'] / 100, 2) }}</td>
                                <td>{{ number_format($row['over_90'] / 100, 2) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="text-center text-body-secondary py-3">{{ __('Nothing outstanding.') }}</td></tr>
                        @endforelse
                    </tbody>
                </table></div>
            </div>
        </div>
    </div>
</div>
