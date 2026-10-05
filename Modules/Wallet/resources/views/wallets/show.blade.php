<div>
    <h4 class="mb-1">{{ __('Wallet') }} — {{ $wallet->student?->first_name }} {{ $wallet->student?->last_name }}</h4>
    <p class="mb-3">{{ __('Balance') }}: <strong class="{{ $wallet->balance_minor < 0 ? 'text-danger' : '' }}">{{ number_format($wallet->balance_minor / 100, 2) }} {{ $wallet->currency }}</strong> — <span class="badge bg-secondary">{{ $wallet->status }}</span></p>

    <div class="row g-4">
        <div class="col-lg-7">
            <div class="card">
                <div class="card-header">{{ __('Transactions') }}</div>
                <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        <thead><tr><th>{{ __('When') }}</th><th>{{ __('Type') }}</th><th>{{ __('Direction') }}</th><th>{{ __('Amount') }}</th><th>{{ __('Balance after') }}</th></tr></thead>
                        <tbody>
                            @forelse ($transactions as $tx)
                                <tr wire:key="tx-{{ $tx->id }}">
                                    <td>{{ $tx->occurred_at->toDateTimeString() }}</td>
                                    <td>{{ $tx->transaction_type }}</td>
                                    <td><span class="badge {{ $tx->direction === 'in' ? 'bg-success' : 'bg-secondary' }}">{{ $tx->direction }}</span></td>
                                    <td>{{ number_format($tx->amount_minor / 100, 2) }}</td>
                                    <td>{{ number_format($tx->balance_after_minor / 100, 2) }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="text-center text-body-secondary py-3">{{ __('No transactions yet.') }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <div class="col-lg-5">
            <div class="card mb-3">
                <div class="card-header">{{ __('Top up') }}</div>
                <div class="card-body">
                    <input type="number" class="form-control mb-2" wire:model="topUpAmountMinor" placeholder="{{ __('Amount (minor units)') }}">
                    <select class="form-select mb-2" wire:model="clearingAccountId">
                        <option value="">{{ __('Clearing account (bank/cash)') }}</option>
                        @foreach ($accounts as $account)
                            <option value="{{ $account->id }}">{{ $account->code }} — {{ $account->name }}</option>
                        @endforeach
                    </select>
                    <button type="button" class="btn btn-primary btn-sm" wire:click="topUp">{{ __('Top up') }}</button>
                </div>
            </div>

            <div class="card mb-3">
                <div class="card-header">{{ __('Spending controls') }}</div>
                <div class="card-body">
                    <select class="form-select mb-2" wire:model="controlGuardianId">
                        <option value="">{{ __('Set by guardian (must be fee-responsible)') }}</option>
                        @foreach ($feeResponsibleGuardians as $link)
                            <option value="{{ $link->guardian_id }}">{{ $link->guardian?->first_name }} {{ $link->guardian?->last_name }}</option>
                        @endforeach
                    </select>
                    <input type="number" class="form-control mb-2" wire:model="dailyLimitMinor" placeholder="{{ __('Daily limit (minor units)') }}">
                    <input type="number" class="form-control mb-2" wire:model="weeklyLimitMinor" placeholder="{{ __('Weekly limit (minor units)') }}">
                    <input type="number" class="form-control mb-2" wire:model="perTransactionLimitMinor" placeholder="{{ __('Per-transaction limit (minor units)') }}">
                    <input type="text" class="form-control mb-2" wire:model="blockedCategories" placeholder="{{ __('Blocked categories, comma separated') }}">
                    <input type="number" class="form-control mb-2" wire:model="lowBalanceThresholdMinor" placeholder="{{ __('Low balance alert threshold') }}">
                    <button type="button" class="btn btn-outline-primary btn-sm" wire:click="setControls">{{ __('Save controls') }}</button>
                </div>
            </div>

            @if ($wallet->status === 'active')
                <div class="card">
                    <div class="card-header">{{ __('Close wallet') }}</div>
                    <div class="card-body">
                        <select class="form-select mb-2" wire:model.live="closePolicy">
                            <option value="refund">{{ __('Refund') }}</option>
                            <option value="transfer_to_fees">{{ __('Transfer to fees') }}</option>
                        </select>
                        @if ($closePolicy === 'refund')
                            <select class="form-select mb-2" wire:model="refundClearingAccountId">
                                <option value="">{{ __('Refund clearing account') }}</option>
                                @foreach ($accounts as $account)
                                    <option value="{{ $account->id }}">{{ $account->code }} — {{ $account->name }}</option>
                                @endforeach
                            </select>
                        @else
                            <select class="form-select mb-2" wire:model="feeDebtorsAccountId">
                                <option value="">{{ __('Fee debtors account') }}</option>
                                @foreach ($feeDebtorsAccounts as $account)
                                    <option value="{{ $account->id }}">{{ $account->code }} — {{ $account->name }}</option>
                                @endforeach
                            </select>
                        @endif
                        <button type="button" class="btn btn-outline-danger btn-sm" wire:click="close">{{ __('Close wallet') }}</button>
                    </div>
                </div>
            @endif
        </div>
    </div>
</div>
