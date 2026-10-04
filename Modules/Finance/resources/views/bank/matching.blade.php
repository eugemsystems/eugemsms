<div>
    <div class="mb-4">
        <h4 class="mb-1">{{ __('Matching workbench') }}</h4>
        <p class="text-body-secondary mb-0">
            {{ __('Statement from :from to :to — :matched of :total lines matched.', [
                'from' => $statement->statement_from->format('d M Y'),
                'to' => $statement->statement_to->format('d M Y'),
                'matched' => $statement->matched_count,
                'total' => $statement->line_count,
            ]) }}
        </p>
    </div>

    <div class="card">
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead>
                    <tr>
                        <th>{{ __('Date') }}</th>
                        <th>{{ __('Description') }}</th>
                        <th>{{ __('Reference') }}</th>
                        <th>{{ __('Debit') }}</th>
                        <th>{{ __('Credit') }}</th>
                        <th>{{ __('Match status') }}</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($lines as $line)
                        <tr wire:key="line-{{ $line->id }}">
                            <td>{{ $line->transaction_date->format('d M Y') }}</td>
                            <td>{{ $line->description }}</td>
                            <td>{{ $line->reference ?? '—' }}</td>
                            <td>{{ $line->debit_minor ? number_format($line->debit_minor / 100, 2) : '—' }}</td>
                            <td>{{ $line->credit_minor ? number_format($line->credit_minor / 100, 2) : '—' }}</td>
                            <td>
                                <span class="badge {{ in_array($line->match_status, ['auto_matched', 'manually_matched']) ? 'text-bg-success' : 'text-bg-secondary' }}">
                                    {{ str_replace('_', ' ', ucfirst($line->match_status)) }}
                                </span>
                            </td>
                            <td class="text-end">
                                @if ($line->match_status === 'unmatched' && $line->isCredit())
                                    <button type="button" class="btn btn-sm btn-outline-primary" wire:click="startMatching({{ $line->id }})">{{ __('Match') }}</button>
                                    <button type="button" class="btn btn-sm btn-outline-warning" wire:click="convertToSuspense({{ $line->id }})" wire:confirm="{{ __('Convert this unmatched credit to a suspense receipt?') }}">{{ __('To suspense') }}</button>
                                @endif
                            </td>
                        </tr>
                        @if ($matchingLineId === $line->id)
                            <tr wire:key="line-{{ $line->id }}-candidates">
                                <td colspan="7" class="bg-light">
                                    @php $candidates = $this->candidatesFor($line); @endphp
                                    @if ($candidates->isEmpty())
                                        <p class="text-body-secondary mb-2">{{ __('No candidate receipts found for this amount/currency within 5 days. You can still convert this line to suspense.') }}</p>
                                    @else
                                        <p class="mb-2">{{ __('Candidate receipts:') }}</p>
                                        <div class="list-group">
                                            @foreach ($candidates as $receipt)
                                                <div class="list-group-item d-flex align-items-center justify-content-between">
                                                    <div>
                                                        {{ $receipt->receipt_number }} — {{ $receipt->payer_name }}
                                                        <span class="text-body-secondary">({{ $receipt->effective_date->format('d M Y') }}, {{ $receipt->currency }} {{ number_format($receipt->amount_minor / 100, 2) }})</span>
                                                    </div>
                                                    <div class="d-flex align-items-center gap-2">
                                                        <span class="badge text-bg-info">{{ $this->suggestedConfidence($line, $receipt) }}% {{ __('match') }}</span>
                                                        <button type="button" class="btn btn-sm btn-success" wire:click="confirmMatch({{ $receipt->id }}, {{ $this->suggestedConfidence($line, $receipt) }})">{{ __('Confirm match') }}</button>
                                                    </div>
                                                </div>
                                            @endforeach
                                        </div>
                                    @endif
                                    <button type="button" class="btn btn-sm btn-link" wire:click="cancelMatching">{{ __('Cancel') }}</button>
                                </td>
                            </tr>
                        @endif
                    @empty
                        <tr>
                            <td colspan="7" class="text-center text-body-secondary py-4">{{ __('This statement has no lines.') }}</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
