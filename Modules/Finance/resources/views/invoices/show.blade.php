<div>
    <div class="d-flex align-items-center gap-2 mb-4">
        <a href="{{ route('finance.invoices.index', $school) }}" class="btn btn-icon btn-outline-secondary btn-sm" wire:navigate>
            <i class="ri ri-arrow-left-line"></i>
        </a>
        <div class="flex-grow-1">
            <h4 class="mb-1">{{ $invoice->invoice_number }}</h4>
            <p class="text-body-secondary mb-0">{{ $invoice->student->admission_number }} — {{ $invoice->student->fullName() }}</p>
        </div>
        <span class="badge fs-6 {{ match ($invoice->status) { 'paid' => 'text-bg-success', 'voided' => 'text-bg-secondary', 'written_off' => 'text-bg-dark', 'overdue' => 'text-bg-danger', default => 'text-bg-warning' } }}">
            {{ \Illuminate\Support\Str::headline($invoice->status) }}
        </span>
        @if (! in_array($invoice->status, ['voided', 'paid', 'written_off']))
            <a href="{{ route('finance.invoices.void', ['school' => $school, 'invoice' => $invoice]) }}" class="btn btn-outline-danger" wire:navigate>{{ __('Void') }}</a>
        @endif
    </div>

    <div class="row g-3">
        <div class="col-lg-8">
            <div class="card mb-3">
                <div class="card-header"><h6 class="mb-0">{{ __('Lines') }}</h6></div>
                <div class="table-responsive">
                    <table class="table mb-0">
                        <thead>
                            <tr>
                                <th>{{ __('Component') }}</th>
                                <th>{{ __('Note') }}</th>
                                <th class="text-end">{{ __('Gross') }}</th>
                                <th class="text-end">{{ __('Discount') }}</th>
                                <th class="text-end">{{ __('Net') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($invoice->lines as $line)
                                <tr wire:key="line-{{ $line->id }}">
                                    <td>{{ $line->component->name }}</td>
                                    <td class="small text-body-secondary">{{ $line->calculation_note }}</td>
                                    <td class="text-end">{{ number_format($line->gross_minor / 100, 2) }}</td>
                                    <td class="text-end">{{ $line->discount_minor > 0 ? number_format($line->discount_minor / 100, 2) : '—' }}</td>
                                    <td class="text-end">{{ number_format($line->net_minor / 100, 2) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="card">
                <div class="card-header"><h6 class="mb-0">{{ __('Allocations') }}</h6></div>
                <div class="table-responsive">
                    <table class="table mb-0">
                        <thead>
                            <tr>
                                <th>{{ __('Receipt') }}</th>
                                <th>{{ __('Method') }}</th>
                                <th class="text-end">{{ __('Amount') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($allocations as $allocation)
                                <tr wire:key="allocation-{{ $allocation->id }}">
                                    <td>{{ $allocation->receipt->receipt_number }}</td>
                                    <td>{{ \Illuminate\Support\Str::headline($allocation->allocation_method) }}</td>
                                    <td class="text-end">{{ number_format($allocation->amount_minor / 100, 2) }} {{ $allocation->currency }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="3" class="text-center text-body-secondary py-4">{{ __('No payments allocated yet.') }}</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card mb-3">
                <div class="card-header"><h6 class="mb-0">{{ __('Summary') }}</h6></div>
                <div class="card-body small">
                    <p class="mb-2"><strong>{{ __('Gross') }}:</strong> {{ number_format($invoice->gross_minor / 100, 2) }}</p>
                    <p class="mb-2"><strong>{{ __('Discount') }}:</strong> {{ number_format($invoice->discount_minor / 100, 2) }}</p>
                    <p class="mb-2"><strong>{{ __('Paid') }}:</strong> {{ number_format($invoice->paid_minor / 100, 2) }}</p>
                    <p class="mb-2"><strong>{{ __('Credited') }}:</strong> {{ number_format($invoice->credited_minor / 100, 2) }}</p>
                    <p class="mb-2"><strong>{{ __('Written off') }}:</strong> {{ number_format($invoice->written_off_minor / 100, 2) }}</p>
                    <p class="mb-0"><strong>{{ __('Balance') }}:</strong> {{ number_format($invoice->balance_minor / 100, 2) }} {{ $invoice->currency }}</p>
                </div>
            </div>

            @if ($invoice->journal)
                <div class="card mb-3">
                    <div class="card-header"><h6 class="mb-0">{{ __('Journal') }}</h6></div>
                    <div class="card-body">
                        <a href="{{ route('finance.journals.show', ['school' => $school, 'journal' => $invoice->journal]) }}" wire:navigate>{{ $invoice->journal->journal_number }}</a>
                    </div>
                </div>
            @endif

            @if ($invoice->status === 'voided')
                <div class="card">
                    <div class="card-body small">
                        <p class="mb-1"><strong>{{ __('Voided') }}:</strong> {{ $invoice->voided_at?->format('d M Y H:i') }}</p>
                        <p class="mb-0"><strong>{{ __('Reason') }}:</strong> {{ $invoice->void_reason }}</p>
                        @if ($invoice->replacedBy)
                            <p class="mb-0 mt-1">
                                <strong>{{ __('Replaced by') }}:</strong>
                                <a href="{{ route('finance.invoices.show', ['school' => $school, 'invoice' => $invoice->replacedBy]) }}" wire:navigate>{{ $invoice->replacedBy->invoice_number }}</a>
                            </p>
                        @endif
                    </div>
                </div>
            @endif
        </div>
    </div>
</div>
