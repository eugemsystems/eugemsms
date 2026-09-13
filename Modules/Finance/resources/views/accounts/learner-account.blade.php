<div>
    <div class="d-flex align-items-center gap-2 mb-4">
        <div class="flex-grow-1">
            <h4 class="mb-1">{{ $student->fullName() }}</h4>
            <p class="text-body-secondary mb-0">{{ $student->admission_number }}</p>
        </div>
        <a href="{{ route('finance.liabilities.editor', ['school' => $school, 'student' => $student]) }}" class="btn btn-outline-secondary btn-sm" wire:navigate>{{ __('Liabilities') }}</a>
        <a href="{{ route('finance.credit-notes.create', $school) }}" class="btn btn-outline-secondary btn-sm" wire:navigate>{{ __('New credit note') }}</a>
        <a href="{{ route('finance.fees.ad-hoc-charge', $school) }}" class="btn btn-outline-secondary btn-sm" wire:navigate>{{ __('Ad hoc charge') }}</a>
    </div>

    <div class="row g-3 mb-3">
        @forelse ($balances as $currency => $balance)
            <div class="col-md-3">
                <div class="card"><div class="card-body">
                    <div class="small text-body-secondary">{{ __('Balance') }} ({{ $currency }})</div>
                    <div class="fs-4 {{ $balance->isNegative() ? 'text-success' : '' }}">{{ number_format($balance->minor / 100, 2) }} {{ $currency }}</div>
                </div></div>
            </div>
        @empty
            <div class="col-12">
                <div class="alert alert-success mb-0">{{ __('No outstanding balance in any currency.') }}</div>
            </div>
        @endforelse
    </div>

    <div class="row g-3">
        <div class="col-lg-6">
            <div class="card">
                <div class="card-header"><h6 class="mb-0">{{ __('Invoices') }}</h6></div>
                <div class="table-responsive">
                    <table class="table mb-0">
                        <thead>
                            <tr>
                                <th>{{ __('Number') }}</th>
                                <th>{{ __('Due') }}</th>
                                <th class="text-end">{{ __('Balance') }}</th>
                                <th>{{ __('Status') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($invoices as $invoice)
                                <tr wire:key="inv-{{ $invoice->id }}">
                                    <td><a href="{{ route('finance.invoices.show', ['school' => $school, 'invoice' => $invoice]) }}" wire:navigate>{{ $invoice->invoice_number }}</a></td>
                                    <td>{{ $invoice->due_date->format('d M Y') }}</td>
                                    <td class="text-end">{{ number_format($invoice->balance_minor / 100, 2) }} {{ $invoice->currency }}</td>
                                    <td><span class="badge {{ match ($invoice->status) { 'paid' => 'text-bg-success', 'voided' => 'text-bg-secondary', default => 'text-bg-warning' } }}">{{ \Illuminate\Support\Str::headline($invoice->status) }}</span></td>
                                </tr>
                            @empty
                                <tr><td colspan="4" class="text-center text-body-secondary py-4">{{ __('No invoices.') }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="col-lg-6">
            <div class="card">
                <div class="card-header"><h6 class="mb-0">{{ __('Receipts') }}</h6></div>
                <div class="table-responsive">
                    <table class="table mb-0">
                        <thead>
                            <tr>
                                <th>{{ __('Number') }}</th>
                                <th class="text-end">{{ __('Amount') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($receipts as $receipt)
                                <tr wire:key="rcpt-{{ $receipt->id }}">
                                    <td>{{ $receipt->receipt_number }}</td>
                                    <td class="text-end">{{ number_format($receipt->amount_minor / 100, 2) }} {{ $receipt->currency }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="2" class="text-center text-body-secondary py-4">{{ __('No receipts.') }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
