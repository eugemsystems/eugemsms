<div>
    <div class="mb-4">
        <h4 class="mb-1">{{ __('Approve exchange rates') }}</h4>
        <p class="text-body-secondary mb-0">{{ __('Rates from a source requiring approval — nothing here has taken effect yet.') }}</p>
    </div>

    <div class="card">
        <div class="table-responsive">
            <table class="table mb-0 align-middle">
                <thead>
                    <tr>
                        <th>{{ __('Pair') }}</th>
                        <th>{{ __('Rate') }}</th>
                        <th>{{ __('Effective from') }}</th>
                        <th>{{ __('Captured by') }}</th>
                        <th class="text-end">{{ __('Actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($pending as $rate)
                        <tr wire:key="pending-{{ $rate->id }}">
                            <td>{{ $rate->from_currency }} → {{ $rate->to_currency }}</td>
                            <td>{{ $rate->rate }}</td>
                            <td>{{ $rate->effective_from->format('d M Y H:i') }}</td>
                            <td>{{ $rate->capturedBy?->name }}</td>
                            <td class="text-end">
                                <div class="d-flex justify-content-end gap-1">
                                    <button type="button" class="btn btn-sm btn-outline-secondary" wire:click="simulate({{ $rate->id }})">
                                        {{ $simulation !== null && $simulatingRateId === $rate->id ? __('Hide impact') : __('View impact') }}
                                    </button>
                                    <button type="button" class="btn btn-sm btn-success" wire:click="approve({{ $rate->id }})" wire:confirm="{{ __('Approve this rate? It becomes active immediately.') }}">
                                        {{ __('Approve') }}
                                    </button>
                                    <button type="button" class="btn btn-sm btn-outline-danger" wire:click="openReject({{ $rate->id }})">
                                        {{ __('Reject') }}
                                    </button>
                                </div>
                            </td>
                        </tr>
                        @if ($simulatingRateId === $rate->id)
                            <tr wire:key="pending-{{ $rate->id }}-simulation">
                                <td colspan="5" class="bg-body-tertiary">
                                    @if ($simulation === null)
                                        <p class="text-body-secondary mb-0 py-2">{{ __('Neither side of this pair is the base currency — there is nothing to simulate against the ledger.') }}</p>
                                    @else
                                        <div class="row g-3 py-2">
                                            <div class="col-md-4">
                                                <div class="small text-body-secondary">{{ __('Total debtors') }}</div>
                                                <div>{{ number_format($simulation->totalDebtorsRecordedMinor / 100, 2) }} → {{ number_format($simulation->totalDebtorsRevaluedMinor / 100, 2) }}</div>
                                            </div>
                                            <div class="col-md-4">
                                                <div class="small text-body-secondary">{{ __('Total creditors') }}</div>
                                                <div>{{ number_format($simulation->totalCreditorsRecordedMinor / 100, 2) }} → {{ number_format($simulation->totalCreditorsRevaluedMinor / 100, 2) }}</div>
                                            </div>
                                            <div class="col-md-4">
                                                <div class="small text-body-secondary">{{ __('FX result') }}</div>
                                                <div class="{{ $simulation->fxResultMinor >= 0 ? 'text-success' : 'text-danger' }}">
                                                    {{ $simulation->fxResultMinor >= 0 ? __('Gain') : __('Loss') }} {{ number_format(abs($simulation->fxResultMinor) / 100, 2) }}
                                                </div>
                                            </div>
                                        </div>
                                    @endif
                                </td>
                            </tr>
                        @endif
                    @empty
                        <tr>
                            <td colspan="5" class="text-center text-body-secondary py-4">{{ __('No rates are waiting for approval.') }}</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    @if ($rejectingRateId !== null)
        <div class="modal show d-block" tabindex="-1" style="background: rgba(0, 0, 0, .5);">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <form wire:submit="reject">
                        <div class="modal-header">
                            <h5 class="modal-title">{{ __('Reject rate') }}</h5>
                            <button type="button" class="btn-close" wire:click="$set('rejectingRateId', null)" aria-label="{{ __('Close') }}"></button>
                        </div>
                        <div class="modal-body">
                            <label class="form-label" for="rejectReason">{{ __('Reason') }}</label>
                            <textarea class="form-control @error('rejectReason') is-invalid @enderror" id="rejectReason" wire:model="rejectReason" rows="3"></textarea>
                            @error('rejectReason') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-outline-secondary" wire:click="$set('rejectingRateId', null)">{{ __('Cancel') }}</button>
                            <button type="submit" class="btn btn-danger" wire:loading.attr="disabled">{{ __('Reject rate') }}</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endif
</div>
