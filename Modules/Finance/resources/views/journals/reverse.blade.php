<div>
    <div class="d-flex align-items-center gap-2 mb-4">
        <a href="{{ route('finance.journals.show', ['school' => $school, 'journal' => $journal]) }}" class="btn btn-icon btn-outline-secondary btn-sm" wire:navigate>
            <i class="ri ri-arrow-left-line"></i>
        </a>
        <div>
            <h4 class="mb-1">{{ __('Reverse :number', ['number' => $journal->journal_number]) }}</h4>
            <p class="text-body-secondary mb-0">{{ __('Creates a new journal with every line flipped — the original is never edited.') }}</p>
        </div>
    </div>

    <div class="row g-3">
        <div class="col-lg-7">
            <div class="card">
                <div class="card-body">
                    <form wire:submit="save">
                        <div class="mb-3">
                            <label class="form-label" for="reason">{{ __('Reason for reversal') }}</label>
                            <textarea class="form-control @error('reason') is-invalid @enderror" id="reason" wire:model="reason" rows="3" placeholder="{{ __('At least 15 characters — this becomes part of the permanent audit trail.') }}"></textarea>
                            @error('reason') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>

                        <div class="form-check form-switch mb-3">
                            <input class="form-check-input" type="checkbox" role="switch" id="overrideCrossPeriod" wire:model="overrideCrossPeriod">
                            <label class="form-check-label" for="overrideCrossPeriod">
                                {{ __('Post the reversal into a different, already-closed period') }}
                            </label>
                        </div>

                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-danger" wire:loading.attr="disabled" wire:confirm="{{ __('Reverse this journal? This cannot be undone.') }}">
                                {{ __('Reverse journal') }}
                            </button>
                            <a href="{{ route('finance.journals.show', ['school' => $school, 'journal' => $journal]) }}" class="btn btn-outline-secondary" wire:navigate>{{ __('Cancel') }}</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-lg-5">
            <div class="card">
                <div class="card-header"><h6 class="mb-0">{{ __('Original lines') }}</h6></div>
                <div class="table-responsive">
                    <table class="table mb-0">
                        <thead>
                            <tr>
                                <th>{{ __('Account') }}</th>
                                <th>{{ __('DR/CR') }}</th>
                                <th class="text-end">{{ __('Amount') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($journal->lines as $line)
                                <tr wire:key="original-line-{{ $line->id }}">
                                    <td>{{ $line->account->code }}</td>
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
    </div>
</div>
