<div>
    <h4 class="mb-1">{{ __('Farm sales') }}</h4>
    <p class="text-body-secondary mb-4">{{ __('External sales of farm surplus — posts directly to the ledger.') }}</p>

    <div class="row g-4">
        <div class="col-lg-7">
            <div class="card">
                <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        <thead><tr><th>{{ __('#') }}</th><th>{{ __('Buyer') }}</th><th>{{ __('Item') }}</th><th>{{ __('Total') }}</th></tr></thead>
                        <tbody>
                            @forelse ($sales as $sale)
                                <tr wire:key="sale-{{ $sale->id }}">
                                    <td>{{ $sale->sale_number }}</td>
                                    <td>{{ $sale->buyer_name }}</td>
                                    <td>{{ $sale->item_description }}</td>
                                    <td>{{ number_format($sale->total_minor / 100, 2) }} {{ $sale->currency }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="4" class="text-center text-body-secondary py-3">{{ __('No sales.') }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <div class="col-lg-5">
            <div class="card">
                <div class="card-header">{{ __('Record sale') }}</div>
                <div class="card-body">
                    <select class="form-select mb-2" wire:model="productionUnitId">
                        <option value="">{{ __('Production unit') }}</option>
                        @foreach ($units as $unit)
                            <option value="{{ $unit->id }}">{{ $unit->name }}</option>
                        @endforeach
                    </select>
                    <input type="text" class="form-control mb-2" wire:model="buyerName" placeholder="{{ __('Buyer name') }}">
                    <input type="text" class="form-control mb-2" wire:model="buyerContact" placeholder="{{ __('Buyer contact (optional)') }}">
                    <input type="text" class="form-control mb-2" wire:model="itemDescription" placeholder="{{ __('Item description') }}">
                    <div class="row g-2 mb-2">
                        <div class="col-4"><input type="number" step="0.001" class="form-control" wire:model="quantity" placeholder="{{ __('Qty') }}"></div>
                        <div class="col-4"><input type="text" class="form-control" wire:model="unit" placeholder="{{ __('Unit') }}"></div>
                        <div class="col-4"><input type="number" class="form-control" wire:model="unitPriceMinor" placeholder="{{ __('Price') }}"></div>
                    </div>
                    <select class="form-select mb-2" wire:model="cashAccountId">
                        <option value="">{{ __('Cash account') }}</option>
                        @foreach ($accounts as $account)
                            <option value="{{ $account->id }}">{{ $account->code }}</option>
                        @endforeach
                    </select>
                    <select class="form-select mb-2" wire:model="salesIncomeAccountId">
                        <option value="">{{ __('Sales income account') }}</option>
                        @foreach ($accounts as $account)
                            <option value="{{ $account->id }}">{{ $account->code }}</option>
                        @endforeach
                    </select>
                    <button type="button" class="btn btn-primary btn-sm" wire:click="record">{{ __('Record sale') }}</button>
                </div>
            </div>
        </div>
    </div>
</div>
