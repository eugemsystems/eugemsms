<div>
    <h4 class="mb-1">{{ __('Change bank details') }} — {{ $supplier->name }}</h4>
    <p class="text-body-secondary mb-4">⚠⚠ {{ __('A different user from whoever requested this must approve it before it takes effect.') }}</p>

    @if ($pending)
        <div class="alert alert-warning">
            {{ __('Pending change: :bank / :branch / :account', ['bank' => $pending['bank_name'], 'branch' => $pending['bank_branch'], 'account' => $pending['account_number']]) }}
            — <button type="button" class="btn btn-sm btn-primary ms-2" wire:click="approve">{{ __('Approve this change') }}</button>
        </div>
    @endif

    <div class="card" style="max-width: 36rem">
        <div class="card-header">{{ __('Request a new change') }}</div>
        <div class="card-body">
            <input type="text" class="form-control mb-2" wire:model="bankName" placeholder="{{ __('Bank name') }}">
            <input type="text" class="form-control mb-2" wire:model="bankBranch" placeholder="{{ __('Branch') }}">
            <input type="text" class="form-control mb-2" wire:model="accountNumber" placeholder="{{ __('Account number') }}">
            <input type="text" class="form-control mb-2" wire:model="accountName" placeholder="{{ __('Account name') }}">
            <input type="text" class="form-control mb-2" wire:model="swiftCode" placeholder="{{ __('SWIFT code (optional)') }}">
            <button type="button" class="btn btn-outline-primary" wire:click="requestChange">{{ __('Submit for approval') }}</button>
        </div>
    </div>
</div>
