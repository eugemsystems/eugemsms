<div>
    <div class="mb-4">
        <h4 class="mb-1">{{ __('Rate impact simulator') }}</h4>
        <p class="text-body-secondary mb-0">{{ __('See the effect of a proposed rate on debtors, creditors, and the FX result before anyone approves it.') }}</p>
    </div>

    <div class="card mb-3">
        <div class="card-body">
            <form wire:submit="preview">
                <div class="row g-3 align-items-end">
                    <div class="col-md-3">
                        <div class="form-floating form-floating-outline">
                            <select class="form-select @error('foreignCurrency') is-invalid @enderror" id="foreignCurrency" wire:model="foreignCurrency">
                                @forelse ($foreignCurrencies as $foreignCurrency)
                                    <option value="{{ $foreignCurrency->currency }}">{{ $foreignCurrency->currency }}</option>
                                @empty
                                    <option value="ZWG">ZWG</option>
                                @endforelse
                            </select>
                            <label for="foreignCurrency">{{ __('Foreign currency') }}</label>
                            @error('foreignCurrency') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-floating form-floating-outline">
                            <input type="text" inputmode="decimal" class="form-control @error('proposedRate') is-invalid @enderror" id="proposedRate" wire:model="proposedRate" placeholder=" ">
                            <label for="proposedRate">{{ __('Proposed rate (1 foreign = ? base)') }}</label>
                            @error('proposedRate') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-floating form-floating-outline">
                            <input type="date" class="form-control @error('asAt') is-invalid @enderror" id="asAt" wire:model="asAt">
                            <label for="asAt">{{ __('As at') }}</label>
                            @error('asAt') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                    </div>
                    <div class="col-md-3">
                        <button type="submit" class="btn btn-primary w-100" wire:loading.attr="disabled">{{ __('Preview') }}</button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    @if ($result !== null)
        <div class="row g-3 mb-3">
            <div class="col-md-4">
                <div class="card"><div class="card-body">
                    <div class="small text-body-secondary">{{ __('Total debtors') }}</div>
                    <div class="fs-5">{{ number_format($result->totalDebtorsRecordedMinor / 100, 2) }} → {{ number_format($result->totalDebtorsRevaluedMinor / 100, 2) }}</div>
                </div></div>
            </div>
            <div class="col-md-4">
                <div class="card"><div class="card-body">
                    <div class="small text-body-secondary">{{ __('Total creditors') }}</div>
                    <div class="fs-5">{{ number_format($result->totalCreditorsRecordedMinor / 100, 2) }} → {{ number_format($result->totalCreditorsRevaluedMinor / 100, 2) }}</div>
                </div></div>
            </div>
            <div class="col-md-4">
                <div class="card"><div class="card-body">
                    <div class="small text-body-secondary">{{ __('FX result') }}</div>
                    <div class="fs-5 {{ $result->fxResultMinor >= 0 ? 'text-success' : 'text-danger' }}">
                        {{ $result->fxResultMinor >= 0 ? __('Gain') : __('Loss') }} {{ number_format(abs($result->fxResultMinor) / 100, 2) }}
                    </div>
                </div></div>
            </div>
        </div>

        <div class="card">
            <div class="card-header"><h6 class="mb-0">{{ __('Per-account detail') }}</h6></div>
            <div class="table-responsive">
                <table class="table mb-0">
                    <thead>
                        <tr>
                            <th>{{ __('Account') }}</th>
                            <th class="text-end">{{ __('Foreign balance') }}</th>
                            <th class="text-end">{{ __('Recorded (base)') }}</th>
                            <th class="text-end">{{ __('Revalued (base)') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($result->accounts as $account)
                            <tr wire:key="sim-account-{{ $account->accountId }}-{{ $account->subledgerId ?? 'na' }}">
                                <td>{{ $account->accountCode }}</td>
                                <td class="text-end">{{ number_format($account->foreignBalanceMinor / 100, 2) }} {{ $account->foreignCurrency }}</td>
                                <td class="text-end">{{ number_format($account->recordedBaseMinor / 100, 2) }}</td>
                                <td class="text-end">{{ number_format($account->revaluedBaseMinor / 100, 2) }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="text-center text-body-secondary py-4">{{ __('No revaluable balances in this currency as at this date.') }}</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    @endif
</div>
