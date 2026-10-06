<div>
    <h4 class="mb-1">{{ __('Inventory items') }}</h4>
    <p class="text-body-secondary mb-4">{{ __('Unit conversions, batch/expiry flags, saleable and capitalisable configuration.') }}</p>

    <div class="row g-4">
        <div class="col-lg-7">
            <div class="card">
                <div class="card-header">
                    <input type="text" class="form-control form-control-sm" style="max-width: 20rem" wire:model.live.debounce.400ms="search" placeholder="{{ __('Search name or code...') }}">
                </div>
                <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        <thead><tr><th>{{ __('Code') }}</th><th>{{ __('Name') }}</th><th>{{ __('Unit') }}</th><th>{{ __('Flags') }}</th></tr></thead>
                        <tbody>
                            @forelse ($items as $item)
                                <tr wire:key="item-{{ $item->id }}">
                                    <td>{{ $item->code }}</td>
                                    <td>{{ $item->name }}</td>
                                    <td>{{ $item->base_unit }}</td>
                                    <td>
                                        @if ($item->is_perishable) <span class="badge text-bg-warning">{{ __('perishable') }}</span> @endif
                                        @if ($item->requires_batch_tracking) <span class="badge text-bg-info">{{ __('batch') }}</span> @endif
                                        @if ($item->is_high_risk) <span class="badge text-bg-danger">⭐ {{ __('high-risk') }}</span> @endif
                                        @if ($item->is_saleable) <span class="badge text-bg-success">{{ __('saleable') }}</span> @endif
                                        @if ($item->is_capitalisable) <span class="badge text-bg-secondary">{{ __('capitalisable') }}</span> @endif
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="4" class="text-center text-body-secondary py-3">{{ __('No items.') }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <div class="col-lg-5">
            <div class="card">
                <div class="card-header">{{ __('New item') }}</div>
                <div class="card-body">
                    <input type="text" class="form-control mb-2" wire:model="code" placeholder="{{ __('Code') }}">
                    <input type="text" class="form-control mb-2" wire:model="name" placeholder="{{ __('Name') }}">
                    <select class="form-select mb-2" wire:model="categoryId">
                        <option value="">{{ __('Category (optional)') }}</option>
                        @foreach ($categories as $category)
                            <option value="{{ $category->id }}">{{ $category->name }}</option>
                        @endforeach
                    </select>
                    <div class="row g-2 mb-2">
                        <div class="col-4"><input type="text" class="form-control" wire:model="baseUnit" placeholder="{{ __('Base unit') }}"></div>
                        <div class="col-4"><input type="text" class="form-control" wire:model="purchaseUnit" placeholder="{{ __('Purchase unit') }}"></div>
                        <div class="col-4"><input type="number" step="0.000001" class="form-control" wire:model="purchaseConversion" placeholder="{{ __('Conv.') }}"></div>
                    </div>
                    <div class="row g-2 mb-2">
                        <div class="col-6"><input type="text" class="form-control" wire:model="issueUnit" placeholder="{{ __('Issue unit') }}"></div>
                        <div class="col-6"><input type="number" step="0.000001" class="form-control" wire:model="issueConversion" placeholder="{{ __('Conv.') }}"></div>
                    </div>
                    <input type="number" class="form-control mb-2" wire:model="shelfLifeDays" placeholder="{{ __('Shelf life (days, optional)') }}">
                    <div class="form-check mb-1">
                        <input type="checkbox" class="form-check-input" id="isPerishable" wire:model="isPerishable">
                        <label class="form-check-label" for="isPerishable">{{ __('Perishable') }}</label>
                    </div>
                    <div class="form-check mb-1">
                        <input type="checkbox" class="form-check-input" id="requiresBatchTracking" wire:model="requiresBatchTracking">
                        <label class="form-check-label" for="requiresBatchTracking">{{ __('Requires batch tracking') }}</label>
                    </div>
                    <div class="form-check mb-1">
                        <input type="checkbox" class="form-check-input" id="isHighRisk" wire:model="isHighRisk">
                        <label class="form-check-label" for="isHighRisk">⭐ {{ __('High-risk (theft-prone)') }}</label>
                    </div>
                    <div class="form-check mb-1">
                        <input type="checkbox" class="form-check-input" id="isSaleable" wire:model.live="isSaleable">
                        <label class="form-check-label" for="isSaleable">{{ __('Saleable to learners (uniform, textbooks)') }}</label>
                    </div>
                    @if ($isSaleable)
                        <input type="number" class="form-control mb-2" wire:model="salePriceMinor" placeholder="{{ __('Sale price (minor units)') }}">
                    @endif
                    <div class="form-check mb-1">
                        <input type="checkbox" class="form-check-input" id="isCapitalisable" wire:model.live="isCapitalisable">
                        <label class="form-check-label" for="isCapitalisable">{{ __('Capitalisable on issue above threshold') }}</label>
                    </div>
                    @if ($isCapitalisable)
                        <input type="number" class="form-control mb-2" wire:model="capitalisationThresholdMinor" placeholder="{{ __('Threshold (minor units, optional — falls back to school default)') }}">
                        <select class="form-select mb-2" wire:model="assetCategoryId">
                            <option value="">{{ __('Asset category (blank = capitalise manually)') }}</option>
                            @foreach ($assetCategories as $category)
                                <option value="{{ $category->id }}">{{ $category->name }}</option>
                            @endforeach
                        </select>
                    @endif
                    <button type="button" class="btn btn-primary btn-sm" wire:click="create">{{ __('Create item') }}</button>
                </div>
            </div>
        </div>
    </div>
</div>
