<div>
    <h4 class="mb-3">{{ __('Spend points') }}</h4>

    <div class="row g-4">
        <div class="col-lg-7">
            <div class="card">
                <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        <thead><tr><th>{{ __('Code') }}</th><th>{{ __('Name') }}</th><th>{{ __('Type') }}</th><th>{{ __('Fiscalisable') }}</th></tr></thead>
                        <tbody>
                            @forelse ($spendPoints as $point)
                                <tr wire:key="point-{{ $point->id }}">
                                    <td>{{ $point->code }}</td>
                                    <td>{{ $point->name }}</td>
                                    <td>{{ $point->point_type }}</td>
                                    <td><span class="badge {{ $point->is_fiscalisable ? 'bg-success' : 'bg-secondary' }}">{{ $point->is_fiscalisable ? __('yes') : __('no') }}</span></td>
                                </tr>
                            @empty
                                <tr><td colspan="4" class="text-center text-body-secondary py-3">{{ __('No spend points yet.') }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <div class="col-lg-5">
            <div class="card">
                <div class="card-header">{{ __('New spend point') }}</div>
                <div class="card-body">
                    <input type="text" class="form-control mb-2" wire:model="code" placeholder="{{ __('Code') }}">
                    <input type="text" class="form-control mb-2" wire:model="name" placeholder="{{ __('Name') }}">
                    <select class="form-select mb-2" wire:model="pointType">
                        <option value="tuckshop">{{ __('Tuckshop') }}</option>
                        <option value="canteen">{{ __('Canteen') }}</option>
                        <option value="stationery">{{ __('Stationery') }}</option>
                        <option value="printing">{{ __('Printing') }}</option>
                        <option value="laundry">{{ __('Laundry') }}</option>
                        <option value="vending">{{ __('Vending') }}</option>
                    </select>
                    <select class="form-select mb-2" wire:model="incomeAccountId">
                        <option value="">{{ __('Income account') }}</option>
                        @foreach ($accounts as $account)
                            <option value="{{ $account->id }}">{{ $account->code }} — {{ $account->name }}</option>
                        @endforeach
                    </select>
                    <select class="form-select mb-2" wire:model="costCentreId">
                        <option value="">{{ __('Cost centre') }}</option>
                        @foreach ($costCentres as $centre)
                            <option value="{{ $centre->id }}">{{ $centre->name }}</option>
                        @endforeach
                    </select>
                    <select class="form-select mb-2" wire:model="storeId">
                        <option value="">{{ __('Stock source store (optional)') }}</option>
                        @foreach ($stores as $store)
                            <option value="{{ $store->id }}">{{ $store->name }}</option>
                        @endforeach
                    </select>
                    <div class="form-check mb-2"><input class="form-check-input" type="checkbox" wire:model="isFiscalisable" id="isFiscalisable"><label class="form-check-label" for="isFiscalisable">{{ __('Fiscalisable') }}</label></div>
                    <button type="button" class="btn btn-primary btn-sm" wire:click="create">{{ __('Create spend point') }}</button>
                </div>
            </div>
        </div>
    </div>
</div>
