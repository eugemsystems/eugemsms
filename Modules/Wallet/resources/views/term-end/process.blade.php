<div>
    <h4 class="mb-1">{{ __('Wallet term-end processing') }} ⚠</h4>
    <p class="text-body-secondary small">{{ __('Carry forward, refund, or transfer to fees. There is no option to recognise a balance as income.') }}</p>

    <div class="row g-2 mb-3 align-items-end" style="max-width:50rem">
        <div class="col-3">
            <select class="form-select" wire:model.live="policy">
                <option value="carry_forward">{{ __('Carry forward') }}</option>
                <option value="refund">{{ __('Refund') }}</option>
                <option value="transfer_to_fees">{{ __('Transfer to fees') }}</option>
            </select>
        </div>
        @if ($policy === 'refund')
            <div class="col-5">
                <select class="form-select" wire:model="refundClearingAccountId">
                    <option value="">{{ __('Refund clearing account') }}</option>
                    @foreach ($accounts as $account)
                        <option value="{{ $account->id }}">{{ $account->code }} — {{ $account->name }}</option>
                    @endforeach
                </select>
            </div>
        @elseif ($policy === 'transfer_to_fees')
            <div class="col-5">
                <select class="form-select" wire:model="feeDebtorsAccountId">
                    <option value="">{{ __('Fee debtors account') }}</option>
                    @foreach ($feeDebtorsAccounts as $account)
                        <option value="{{ $account->id }}">{{ $account->code }} — {{ $account->name }}</option>
                    @endforeach
                </select>
            </div>
        @endif
        <div class="col-auto"><button type="button" class="btn btn-outline-secondary btn-sm" wire:click="toggleAll">{{ __('Select / deselect all') }}</button></div>
        <div class="col-auto"><button type="button" class="btn btn-primary btn-sm" wire:click="commit">{{ __('Process selected') }}</button></div>
    </div>

    @if ($committed)
        <div class="alert alert-success">{{ __(':count wallet(s) processed.', ['count' => $processedCount]) }}</div>
    @endif

    <div class="card">
        <div class="table-responsive">
            <table class="table table-sm mb-0">
                <thead><tr><th></th><th>{{ __('Student') }}</th><th>{{ __('Balance') }}</th></tr></thead>
                <tbody>
                    @forelse ($wallets as $wallet)
                        <tr wire:key="termend-{{ $wallet->id }}">
                            <td><input type="checkbox" wire:model="selectedWalletIds" value="{{ $wallet->id }}"></td>
                            <td>{{ $wallet->student?->first_name }} {{ $wallet->student?->last_name }}</td>
                            <td>{{ number_format($wallet->balance_minor / 100, 2) }} {{ $wallet->currency }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="3" class="text-center text-body-secondary py-3">{{ __('No wallets with a non-zero balance.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
