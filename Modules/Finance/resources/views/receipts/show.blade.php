<div>
    <div class="d-flex align-items-center justify-content-between mb-4">
        <div>
            <h4 class="mb-1">{{ __('Receipt :number', ['number' => $receipt->receipt_number]) }}</h4>
            <p class="text-body-secondary mb-0">{{ $receipt->received_at->format('d M Y H:i') }} — {{ $receipt->payer_name }}</p>
        </div>
        <div class="d-flex gap-2">
            @if ($receipt->status === 'posted')
                <a href="{{ route('finance.receipts.void', ['school' => $school, 'receipt' => $receipt]) }}" class="btn btn-outline-danger btn-sm" wire:navigate>{{ __('Void') }}</a>
            @endif
        </div>
    </div>

    @if ($receipt->status === 'voided')
        <div class="alert alert-danger">{{ __('This receipt was voided on :date — :reason', ['date' => $receipt->voided_at?->format('d M Y H:i'), 'reason' => $receipt->void_reason]) }}</div>
    @endif

    @if ($receipt->is_suspense)
        <div class="alert alert-warning">{{ __('This receipt is unidentified — held in suspense until a cashier matches it to a learner.') }}</div>
    @endif

    <div class="row g-3">
        <div class="col-md-6">
            <div class="card h-100">
                <div class="card-header"><h6 class="mb-0">{{ __('Summary') }}</h6></div>
                <div class="card-body">
                    <dl class="row mb-0">
                        <dt class="col-5">{{ __('Learner') }}</dt>
                        <dd class="col-7">{{ $receipt->student?->fullName() ?? __('— unidentified —') }}</dd>
                        <dt class="col-5">{{ __('Amount') }}</dt>
                        <dd class="col-7">{{ $receipt->currency }} {{ number_format($receipt->amount_minor / 100, 2) }}</dd>
                        <dt class="col-5">{{ __('Allocated') }}</dt>
                        <dd class="col-7">{{ number_format($receipt->allocated_minor / 100, 2) }}</dd>
                        <dt class="col-5">{{ __('Unallocated') }}</dt>
                        <dd class="col-7">{{ number_format($receipt->unallocated_minor / 100, 2) }}</dd>
                        <dt class="col-5">{{ __('Till session') }}</dt>
                        <dd class="col-7">{{ $receipt->tillSession?->till?->code ?? __('—') }}</dd>
                        <dt class="col-5">{{ __('Received by') }}</dt>
                        <dd class="col-7">{{ $receipt->receivedBy->name }}</dd>
                    </dl>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card h-100">
                <div class="card-header"><h6 class="mb-0">{{ __('Tenders') }}</h6></div>
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0">
                        <thead>
                            <tr>
                                <th>{{ __('Type') }}</th>
                                <th>{{ __('Reference') }}</th>
                                <th class="text-end">{{ __('Amount') }}</th>
                                <th>{{ __('Cleared') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($receipt->tenders as $tender)
                                <tr>
                                    <td>{{ ucfirst(str_replace('_', ' ', $tender->tender_type)) }}</td>
                                    <td>{{ $tender->reference ?? '—' }}</td>
                                    <td class="text-end">{{ number_format($tender->amount_minor / 100, 2) }}</td>
                                    <td>
                                        @if ($tender->is_cleared)
                                            <span class="badge bg-label-success">{{ __('Cleared') }}</span>
                                        @else
                                            <span class="badge bg-label-warning">{{ __('Uncleared') }}</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div class="card mt-3">
        <div class="card-header"><h6 class="mb-0">{{ __('Allocations') }}</h6></div>
        <div class="table-responsive">
            <table class="table table-sm align-middle mb-0">
                <thead>
                    <tr>
                        <th>{{ __('Invoice') }}</th>
                        <th>{{ __('Component') }}</th>
                        <th class="text-end">{{ __('Amount') }}</th>
                        <th>{{ __('Status') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($receipt->allocations as $allocation)
                        <tr>
                            <td>{{ $allocation->invoice->invoice_number }}</td>
                            <td>{{ $allocation->component->name }}</td>
                            <td class="text-end">{{ number_format($allocation->amount_minor / 100, 2) }}</td>
                            <td>
                                @if ($allocation->isReversed())
                                    <span class="badge bg-label-danger">{{ __('Reversed') }}</span>
                                @else
                                    <span class="badge bg-label-success">{{ __('Active') }}</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="text-center text-body-secondary py-4">{{ __('No allocations — this receipt is unidentified or fully overpaid to credit balance.') }}</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
