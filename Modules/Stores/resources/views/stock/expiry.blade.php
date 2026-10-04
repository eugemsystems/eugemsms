<div>
    <h4 class="mb-1">{{ __('Expiry monitor') }}</h4>
    <p class="text-body-secondary mb-4">{{ __('Expired stock cannot be issued — it is written off here, at its own cost, to a wastage account.') }}</p>

    <h6 class="mb-2">{{ __('Approaching expiry') }}</h6>
    <div class="card mb-4">
        <div class="table-responsive">
            <table class="table table-sm mb-0">
                <thead><tr><th>{{ __('Item') }}</th><th>{{ __('Store') }}</th><th>{{ __('Lot') }}</th><th>{{ __('Expiry date') }}</th><th>{{ __('Remaining') }}</th></tr></thead>
                <tbody>
                    @forelse ($approaching as $lot)
                        <tr wire:key="approaching-{{ $lot->id }}">
                            <td>{{ $lot->item->name }}</td>
                            <td>{{ $lot->store->code }}</td>
                            <td>{{ $lot->lot_reference }}</td>
                            <td>{{ $lot->expiry_date->format('Y-m-d') }}</td>
                            <td>{{ $lot->quantity_remaining }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="text-center text-body-secondary py-3">{{ __('Nothing approaching expiry.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <h6 class="mb-2">{{ __('Already expired — write off') }}</h6>
    <div class="card">
        <div class="card-header">
            <select class="form-select form-select-sm" style="max-width: 24rem" wire:model="wastageAccountId">
                <option value="">{{ __('Wastage account') }}</option>
                @foreach ($accounts as $account)
                    <option value="{{ $account->id }}">{{ $account->code }} — {{ $account->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="table-responsive">
            <table class="table table-sm mb-0">
                <thead><tr><th>{{ __('Item') }}</th><th>{{ __('Store') }}</th><th>{{ __('Lot') }}</th><th>{{ __('Remaining') }}</th><th>{{ __('Qty to write off') }}</th><th>{{ __('Reason') }}</th><th></th></tr></thead>
                <tbody>
                    @forelse ($expired as $lot)
                        <tr wire:key="expired-{{ $lot->id }}">
                            <td>{{ $lot->item->name }}</td>
                            <td>{{ $lot->store->code }}</td>
                            <td>{{ $lot->lot_reference }}</td>
                            <td>{{ $lot->quantity_remaining }}</td>
                            <td><input type="number" step="0.0001" class="form-control form-control-sm" wire:model="writeOffQuantities.{{ $lot->id }}"></td>
                            <td><input type="text" class="form-control form-control-sm" wire:model="writeOffReasons.{{ $lot->id }}"></td>
                            <td><button type="button" class="btn btn-sm btn-outline-danger" wire:click="writeOff({{ $lot->id }})">{{ __('Write off') }}</button></td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="text-center text-body-secondary py-3">{{ __('No expired stock.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
