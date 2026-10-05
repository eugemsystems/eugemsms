<div>
    <h4 class="mb-3">{{ __('Wallet products') }}</h4>

    <div class="row g-4">
        <div class="col-lg-7">
            <div class="card">
                <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        <thead><tr><th>{{ __('Code') }}</th><th>{{ __('Name') }}</th><th>{{ __('Spend point') }}</th><th>{{ __('Category') }}</th><th>{{ __('Price') }}</th></tr></thead>
                        <tbody>
                            @forelse ($products as $product)
                                <tr wire:key="product-{{ $product->id }}">
                                    <td>{{ $product->code }}</td>
                                    <td>{{ $product->name }}</td>
                                    <td>{{ $product->spendPoint->name }}</td>
                                    <td>{{ $product->category }}</td>
                                    <td>{{ number_format($product->price_minor / 100, 2) }} {{ $product->currency }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="text-center text-body-secondary py-3">{{ __('No wallet products yet.') }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <div class="col-lg-5">
            <div class="card">
                <div class="card-header">{{ __('New product') }}</div>
                <div class="card-body">
                    <select class="form-select mb-2" wire:model="spendPointId">
                        <option value="">{{ __('Spend point') }}</option>
                        @foreach ($spendPoints as $point)
                            <option value="{{ $point->id }}">{{ $point->name }}</option>
                        @endforeach
                    </select>
                    <input type="text" class="form-control mb-2" wire:model="code" placeholder="{{ __('Code') }}">
                    <input type="text" class="form-control mb-2" wire:model="name" placeholder="{{ __('Name') }}">
                    <input type="text" class="form-control mb-2" wire:model="category" placeholder="{{ __('Category (e.g. confectionery)') }}">
                    <input type="number" class="form-control mb-2" wire:model="priceMinor" placeholder="{{ __('Price (minor units)') }}">
                    <select class="form-select mb-2" wire:model="currency">
                        <option value="USD">USD</option>
                        <option value="ZWG">ZWG</option>
                    </select>
                    <select class="form-select mb-2" wire:model="taxType">
                        <option value="standard">{{ __('Standard') }}</option>
                        <option value="zero_rated">{{ __('Zero rated') }}</option>
                        <option value="exempt">{{ __('Exempt') }}</option>
                        <option value="withholding">{{ __('Withholding') }}</option>
                    </select>
                    <select class="form-select mb-2" wire:model="itemId">
                        <option value="">{{ __('Linked inventory item (optional)') }}</option>
                        @foreach ($items as $item)
                            <option value="{{ $item->id }}">{{ $item->name }}</option>
                        @endforeach
                    </select>
                    <input type="text" class="form-control mb-2" wire:model="barcode" placeholder="{{ __('Barcode (optional)') }}">
                    <button type="button" class="btn btn-primary btn-sm" wire:click="create">{{ __('Create product') }}</button>
                </div>
            </div>
        </div>
    </div>
</div>
