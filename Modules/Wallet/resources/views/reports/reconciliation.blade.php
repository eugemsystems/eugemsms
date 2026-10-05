<div>
    <h4 class="mb-1">{{ __('Wallet liability reconciliation') }} ⭐</h4>
    <p class="text-body-secondary small">{{ __('The sum of every active wallet balance must equal the true general-ledger balance of the liability account(s), computed fresh from journal lines.') }}</p>

    <button type="button" class="btn btn-primary btn-sm mb-3" wire:click="reconcile">{{ __('Run reconciliation now') }}</button>

    @if ($result)
        <div class="alert {{ $result['variance_minor'] === 0 ? 'alert-success' : 'alert-danger' }}">
            <div>{{ __('Sum of wallet balances:') }} {{ number_format($result['sum_of_balances_minor'] / 100, 2) }}</div>
            <div>{{ __('Liability account balance:') }} {{ number_format($result['liability_account_balance_minor'] / 100, 2) }}</div>
            <div><strong>{{ __('Variance:') }} {{ number_format($result['variance_minor'] / 100, 2) }}</strong></div>
        </div>
    @endif
</div>
