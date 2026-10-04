<div>
    <h4 class="mb-1">{{ __('Dispose asset') }}</h4>
    <p class="text-body-secondary mb-4">{{ __('Gain or loss computes against net book value at the disposal date.') }}</p>

    <div class="card" style="max-width: 40rem">
        <div class="card-body">
            <select class="form-select mb-2" wire:model.live="assetId">
                <option value="">{{ __('Asset') }}</option>
                @foreach ($assets as $asset)
                    <option value="{{ $asset->id }}">{{ $asset->name }} ({{ $asset->asset_tag }}) — NBV {{ number_format($asset->net_book_value_minor / 100, 2) }}</option>
                @endforeach
            </select>
            <div class="row g-2 mb-2">
                <div class="col-6"><input type="date" class="form-control" wire:model="disposalDate"></div>
                <div class="col-6">
                    <select class="form-select" wire:model="disposalMethod">
                        <option value="sale">{{ __('Sale') }}</option>
                        <option value="scrap">{{ __('Scrap') }}</option>
                        <option value="donation">{{ __('Donation') }}</option>
                        <option value="trade_in">{{ __('Trade-in') }}</option>
                        <option value="loss">{{ __('Loss') }}</option>
                        <option value="theft">{{ __('Theft') }}</option>
                    </select>
                </div>
            </div>
            <input type="number" class="form-control mb-2" wire:model.live="proceedsMinor" placeholder="{{ __('Proceeds (minor units)') }}">
            @if ($proceedsMinor > 0)
                <select class="form-select mb-2" wire:model="proceedsAccountId">
                    <option value="">{{ __('Proceeds account') }}</option>
                    @foreach ($accounts as $account)
                        <option value="{{ $account->id }}">{{ $account->code }} — {{ $account->name }}</option>
                    @endforeach
                </select>
            @endif
            <input type="text" class="form-control mb-2" wire:model="buyer" placeholder="{{ __('Buyer (optional)') }}">
            <textarea class="form-control mb-2" wire:model="reason" placeholder="{{ __('Reason') }}"></textarea>

            @if ($gainLossPreviewMinor !== null)
                <div class="alert alert-light border mb-2">
                    {{ $gainLossPreviewMinor >= 0 ? __('Estimated gain') : __('Estimated loss') }}: {{ number_format(abs($gainLossPreviewMinor) / 100, 2) }}
                </div>
            @endif

            <div class="form-check mb-2">
                <input type="checkbox" class="form-check-input" id="confirmApproval" wire:model="confirmApproval">
                <label class="form-check-label" for="confirmApproval">{{ __('I am approving this disposal (required above the configured threshold)') }}</label>
            </div>

            <button type="button" class="btn btn-primary" wire:click="dispose">{{ __('Dispose') }}</button>
        </div>
    </div>
</div>
