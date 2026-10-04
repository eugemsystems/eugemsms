<div>
    <h4 class="mb-1">{{ __('Receive stock') }}</h4>
    <p class="text-body-secondary mb-4">{{ __('Opening stock or a direct receipt — no purchase order required. Creates a lot and posts a journal in the same transaction.') }}</p>

    <div class="card" style="max-width: 40rem">
        <div class="card-body">
            <select class="form-select mb-2" wire:model="storeId">
                <option value="">{{ __('Store') }}</option>
                @foreach ($stores as $store)
                    <option value="{{ $store->id }}">{{ $store->code }} — {{ $store->name }}</option>
                @endforeach
            </select>
            <select class="form-select mb-2" wire:model="itemId">
                <option value="">{{ __('Item') }}</option>
                @foreach ($items as $item)
                    <option value="{{ $item->id }}">{{ $item->name }}</option>
                @endforeach
            </select>
            <div class="row g-2 mb-2">
                <div class="col-4"><input type="number" step="0.0001" class="form-control" wire:model="quantity" placeholder="{{ __('Quantity') }}"></div>
                <div class="col-4"><input type="number" class="form-control" wire:model="unitCostMinor" placeholder="{{ __('Unit cost (minor)') }}"></div>
                <div class="col-4">
                    <select class="form-select" wire:model="currency">
                        <option value="USD">USD</option>
                        <option value="ZWG">ZWG</option>
                    </select>
                </div>
            </div>
            <input type="date" class="form-control mb-2" wire:model="receivedOn">
            <select class="form-select mb-2" wire:model="contraAccountId">
                <option value="">{{ __('Contra account (credit side)') }}</option>
                @foreach ($accounts as $account)
                    <option value="{{ $account->id }}">{{ $account->code }} — {{ $account->name }}</option>
                @endforeach
            </select>
            <select class="form-select mb-2" wire:model="sourceType">
                <option value="opening">{{ __('Opening balance') }}</option>
                <option value="adjustment">{{ __('Manual receipt') }}</option>
            </select>
            <div class="row g-2 mb-2">
                <div class="col-6"><input type="text" class="form-control" wire:model="batchNumber" placeholder="{{ __('Batch number (if required)') }}"></div>
                <div class="col-6"><input type="date" class="form-control" wire:model="expiryDate" placeholder="{{ __('Expiry date (if perishable)') }}"></div>
            </div>
            <button type="button" class="btn btn-primary" wire:click="receive">{{ __('Receive') }}</button>
        </div>
    </div>
</div>
