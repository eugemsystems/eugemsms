<div>
    <div class="mb-4">
        <h4 class="mb-1">{{ __('Generate statement') }}</h4>
        <p class="text-body-secondary mb-0">{{ __('Built from journal lines for the date range — reproducible identically forever, even for a closed period.') }}</p>
    </div>

    <div class="card mb-3">
        <div class="card-body">
            <form wire:submit="generate">
                <div class="row g-3 mb-3">
                    <div class="col-md-3">
                        <div class="form-floating form-floating-outline">
                            <select class="form-select" id="subledgerType" wire:model.live="subledgerType">
                                <option value="student">{{ __('Learner') }}</option>
                                <option value="guardian">{{ __('Guardian') }}</option>
                            </select>
                            <label for="subledgerType">{{ __('Statement for') }}</label>
                        </div>
                    </div>
                    <div class="col-md-9">
                        <label class="form-label">{{ __(':type', ['type' => $subledgerType === 'student' ? __('Learner') : __('Guardian')]) }}</label>
                        @if ($selectedPartyId !== null)
                            <div class="input-group">
                                <input type="text" class="form-control" value="{{ $selectedPartyLabel }}" disabled>
                                <button type="button" class="btn btn-outline-secondary" wire:click="$set('selectedPartyId', null)">{{ __('Change') }}</button>
                            </div>
                        @else
                            <input type="text" class="form-control" wire:model.live.debounce.300ms="partySearch" placeholder="{{ __('Search…') }}">
                            @if ($partySearch !== '')
                                <div class="list-group mt-1">
                                    @forelse ($this->partyResults() as $result)
                                        <button type="button" wire:key="party-{{ $result->id }}" class="list-group-item list-group-item-action" wire:click="selectParty({{ $result->id }})">
                                            {{ $subledgerType === 'student' ? "{$result->admission_number} — {$result->fullName()}" : $result->displayName() }}
                                        </button>
                                    @empty
                                        <div class="list-group-item text-body-secondary">{{ __('No matches.') }}</div>
                                    @endforelse
                                </div>
                            @endif
                        @endif
                    </div>
                </div>

                <div class="row g-3 align-items-end">
                    <div class="col-md-3">
                        <div class="form-floating form-floating-outline">
                            <select class="form-select" id="currency" wire:model="currency">
                                <option value="USD">USD</option>
                                <option value="ZWG">ZWG</option>
                            </select>
                            <label for="currency">{{ __('Currency') }}</label>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-floating form-floating-outline">
                            <input type="date" class="form-control" id="from" wire:model="from">
                            <label for="from">{{ __('From') }}</label>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-floating form-floating-outline">
                            <input type="date" class="form-control" id="to" wire:model="to">
                            <label for="to">{{ __('To') }}</label>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <button type="submit" class="btn btn-primary w-100" wire:loading.attr="disabled">{{ __('Generate') }}</button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    @if ($statement !== null)
        <div class="card">
            <div class="card-header d-flex justify-content-between">
                <span>{{ __('Opening balance') }}: {{ number_format($statement->openingBalanceMinor / 100, 2) }} {{ $statement->currency }}</span>
                <span>{{ __('Closing balance') }}: {{ number_format($statement->closingBalanceMinor / 100, 2) }} {{ $statement->currency }}</span>
            </div>
            <div class="table-responsive">
                <table class="table mb-0">
                    <thead>
                        <tr>
                            <th>{{ __('Date') }}</th>
                            <th>{{ __('Reference') }}</th>
                            <th>{{ __('Narration') }}</th>
                            <th>{{ __('DR/CR') }}</th>
                            <th class="text-end">{{ __('Amount') }}</th>
                            <th class="text-end">{{ __('Balance') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($statement->lines as $line)
                            <tr wire:key="stmt-{{ $loop->index }}">
                                <td>{{ $line->effectiveAt }}</td>
                                <td>{{ $line->journalNumber }}</td>
                                <td>{{ $line->narration }}</td>
                                <td><span class="badge {{ $line->direction === 'DR' ? 'text-bg-primary' : 'text-bg-warning' }}">{{ $line->direction }}</span></td>
                                <td class="text-end">{{ number_format($line->amountMinor / 100, 2) }}</td>
                                <td class="text-end">{{ number_format($line->runningBalanceMinor / 100, 2) }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="text-center text-body-secondary py-4">{{ __('No activity in this period.') }}</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    @endif
</div>
