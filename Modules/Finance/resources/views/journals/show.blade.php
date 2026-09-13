<div>
    <div class="d-flex align-items-center gap-2 mb-4">
        <a href="{{ route('finance.journals.index', $school) }}" class="btn btn-icon btn-outline-secondary btn-sm" wire:navigate>
            <i class="ri ri-arrow-left-line"></i>
        </a>
        <div class="flex-grow-1">
            <h4 class="mb-1">{{ $journal->journal_number }}</h4>
            <p class="text-body-secondary mb-0">{{ $journal->journal_type }} · {{ __('Effective') }} {{ $journal->effective_at->format('d M Y') }}</p>
        </div>
        <span class="badge fs-6 {{ $journal->status === 'posted' ? 'text-bg-success' : 'text-bg-warning' }}">
            {{ \Illuminate\Support\Str::headline($journal->status) }}
        </span>
        @if ($journal->isReversed())
            <span class="badge fs-6 text-bg-secondary">{{ __('Reversed') }}</span>
        @endif
    </div>

    <div class="row g-3">
        <div class="col-lg-8">
            <div class="card mb-3">
                <div class="card-header"><h6 class="mb-0">{{ __('Details') }}</h6></div>
                <div class="card-body">
                    <p>{{ $journal->narration }}</p>
                    @if ($journal->reference)
                        <p class="mb-2"><strong>{{ __('Reference') }}:</strong> {{ $journal->reference }}</p>
                    @endif
                    @if ($journal->reversesJournal)
                        <p class="mb-2">
                            <strong>{{ __('Reverses') }}:</strong>
                            <a href="{{ route('finance.journals.show', ['school' => $school, 'journal' => $journal->reversesJournal]) }}" wire:navigate>{{ $journal->reversesJournal->journal_number }}</a>
                        </p>
                    @endif
                    @if ($journal->reversedByJournal)
                        <p class="mb-0">
                            <strong>{{ __('Reversed by') }}:</strong>
                            <a href="{{ route('finance.journals.show', ['school' => $school, 'journal' => $journal->reversedByJournal]) }}" wire:navigate>{{ $journal->reversedByJournal->journal_number }}</a>
                        </p>
                    @endif
                </div>
            </div>

            <div class="card">
                <div class="card-header"><h6 class="mb-0">{{ __('Lines') }}</h6></div>
                <div class="table-responsive">
                    <table class="table mb-0">
                        <thead>
                            <tr>
                                <th>{{ __('Account') }}</th>
                                <th>{{ __('Cost centre') }}</th>
                                <th>{{ __('DR/CR') }}</th>
                                <th class="text-end">{{ __('Amount') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($journal->lines as $line)
                                <tr wire:key="line-{{ $line->id }}">
                                    <td>{{ $line->account->code }} — {{ $line->account->name }}</td>
                                    <td>{{ $line->costCentre?->name ?? '—' }}</td>
                                    <td>
                                        <span class="badge {{ $line->direction === 'DR' ? 'text-bg-primary' : 'text-bg-warning' }}">{{ $line->direction }}</span>
                                    </td>
                                    <td class="text-end">{{ number_format($line->amount_minor / 100, 2) }} {{ $line->currency }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card mb-3">
                <div class="card-header"><h6 class="mb-0">{{ __('Audit') }}</h6></div>
                <div class="card-body small">
                    <p class="mb-2"><strong>{{ __('Posted by') }}:</strong> {{ $journal->postedBy?->name }}</p>
                    @if ($journal->approvedBy)
                        <p class="mb-0"><strong>{{ __('Approved by') }}:</strong> {{ $journal->approvedBy->name }}</p>
                    @endif
                </div>
            </div>

            @if ($this->canApprove())
                <div class="card mb-3">
                    <div class="card-body">
                        <p class="small text-body-secondary">{{ __('Approving posts this journal — it becomes real and cannot be edited, only reversed.') }}</p>
                        <button type="button" class="btn btn-success w-100" wire:click="approve" wire:confirm="{{ __('Approve and post this journal?') }}" wire:loading.attr="disabled">
                            {{ __('Approve & post') }}
                        </button>
                    </div>
                </div>
            @endif

            @if ($this->canReverse())
                <div class="card">
                    <div class="card-body">
                        <a href="{{ route('finance.journals.reverse', ['school' => $school, 'journal' => $journal]) }}" class="btn btn-outline-danger w-100" wire:navigate>
                            {{ __('Reverse this journal') }}
                        </a>
                    </div>
                </div>
            @endif
        </div>
    </div>
</div>
