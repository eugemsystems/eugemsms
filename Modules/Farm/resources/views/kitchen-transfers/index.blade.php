<div>
    <h4 class="mb-1">{{ __('Kitchen transfers') }} ⭐</h4>
    <p class="text-body-secondary mb-4">{{ __('Never a free transfer — moves stock between the farm and kitchen stores at internal cost, posting a real journal. Milk/meat within a withdrawal period is blocked.') }}</p>

    <div class="row g-4">
        <div class="col-lg-7">
            <div class="card">
                <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        <thead><tr><th>{{ __('#') }}</th><th>{{ __('Date') }}</th><th>{{ __('Qty') }}</th><th>{{ __('Cost') }}</th><th>{{ __('Market value') }}</th></tr></thead>
                        <tbody>
                            @forelse ($transfers as $transfer)
                                <tr wire:key="transfer-{{ $transfer->id }}">
                                    <td>{{ $transfer->transfer_number }}</td>
                                    <td>{{ $transfer->transfer_date->toFormattedDateString() }}</td>
                                    <td>{{ $transfer->quantity }} {{ $transfer->unit }}</td>
                                    <td>{{ number_format($transfer->total_cost_minor / 100, 2) }}</td>
                                    <td>{{ $transfer->market_price_minor !== null ? number_format($transfer->quantity * $transfer->market_price_minor / 100, 2) : '—' }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="text-center text-body-secondary py-3">{{ __('No transfers.') }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <div class="col-lg-5">
            <div class="card">
                <div class="card-header">{{ __('New transfer') }}</div>
                <div class="card-body">
                    <select class="form-select mb-2" wire:model.live="productionUnitId">
                        <option value="">{{ __('Production unit') }}</option>
                        @foreach ($units as $unit)
                            <option value="{{ $unit->id }}">{{ $unit->name }}</option>
                        @endforeach
                    </select>
                    @if ($outputs->isNotEmpty())
                        <select class="form-select mb-2" wire:model="outputId">
                            <option value="">{{ __('Link to a milk/meat output (enforces withdrawal period)') }}</option>
                            @foreach ($outputs as $output)
                                <option value="{{ $output->id }}">{{ $output->output_date->toFormattedDateString() }} — {{ $output->output_type }} ({{ $output->quantity }} {{ $output->unit }})</option>
                            @endforeach
                        </select>
                    @endif
                    <select class="form-select mb-2" wire:model="fromStoreId">
                        <option value="">{{ __('From store (farm)') }}</option>
                        @foreach ($stores as $store)
                            <option value="{{ $store->id }}">{{ $store->code }}</option>
                        @endforeach
                    </select>
                    <select class="form-select mb-2" wire:model="toStoreId">
                        <option value="">{{ __('To store (kitchen)') }}</option>
                        @foreach ($stores as $store)
                            <option value="{{ $store->id }}">{{ $store->code }}</option>
                        @endforeach
                    </select>
                    <select class="form-select mb-2" wire:model="itemId">
                        <option value="">{{ __('Item') }}</option>
                        @foreach ($items as $item)
                            <option value="{{ $item->id }}">{{ $item->name }}</option>
                        @endforeach
                    </select>
                    <input type="number" step="0.001" class="form-control mb-2" wire:model="quantity" placeholder="{{ __('Quantity') }}">
                    <input type="text" class="form-control mb-2" wire:model="unit" placeholder="{{ __('Unit') }}">
                    <input type="number" class="form-control mb-2" wire:model="marketPriceMinor" placeholder="{{ __('Market price per unit (minor units, optional — for savings report)') }}">
                    <button type="button" class="btn btn-primary btn-sm" wire:click="transfer">{{ __('Transfer to kitchen') }}</button>
                </div>
            </div>
        </div>
    </div>
</div>
