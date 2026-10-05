<div>
    <h4 class="mb-1">{{ __('Harvest') }}</h4>
    <p class="text-body-secondary mb-4">{{ __('Cost per kg is the internal transfer price — total cycle cost divided by cumulative actual yield.') }}</p>

    <div class="row g-4">
        <div class="col-lg-7">
            <div class="card">
                <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        <thead><tr><th>{{ __('Cycle') }}</th><th>{{ __('Date') }}</th><th>{{ __('Qty (kg)') }}</th><th>{{ __('Cost/kg') }}</th><th>{{ __('Destination') }}</th></tr></thead>
                        <tbody>
                            @forelse ($harvests as $harvest)
                                <tr wire:key="harvest-{{ $harvest->id }}">
                                    <td>{{ $harvest->cropCycle->cycle_reference }}</td>
                                    <td>{{ $harvest->harvested_on->toFormattedDateString() }}</td>
                                    <td>{{ $harvest->quantity_kg }}</td>
                                    <td>{{ number_format($harvest->unit_cost_minor / 100, 2) }}</td>
                                    <td>{{ $harvest->destination }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="text-center text-body-secondary py-3">{{ __('No harvests.') }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <div class="col-lg-5">
            <div class="card">
                <div class="card-header">{{ __('Record harvest') }}</div>
                <div class="card-body">
                    <select class="form-select mb-2" wire:model="cropCycleId">
                        <option value="">{{ __('Crop cycle') }}</option>
                        @foreach ($cycles as $cycle)
                            <option value="{{ $cycle->id }}">{{ $cycle->cycle_reference }} — {{ $cycle->crop }}</option>
                        @endforeach
                    </select>
                    <input type="number" step="0.01" class="form-control mb-2" wire:model="quantityKg" placeholder="{{ __('Quantity (kg)') }}">
                    <select class="form-select mb-2" wire:model="itemId">
                        <option value="">{{ __('Stock item') }}</option>
                        @foreach ($items as $item)
                            <option value="{{ $item->id }}">{{ $item->name }}</option>
                        @endforeach
                    </select>
                    <select class="form-select mb-2" wire:model="farmProductionContraAccountId">
                        <option value="">{{ __('Farm production contra account') }}</option>
                        @foreach ($accounts as $account)
                            <option value="{{ $account->id }}">{{ $account->code }}</option>
                        @endforeach
                    </select>
                    <select class="form-select mb-2" wire:model="destination">
                        @foreach (['store', 'kitchen', 'sale', 'seed', 'waste'] as $dest)
                            <option value="{{ $dest }}">{{ $dest }}</option>
                        @endforeach
                    </select>
                    <input type="text" class="form-control mb-2" wire:model="qualityGrade" placeholder="{{ __('Quality grade (optional)') }}">
                    <button type="button" class="btn btn-primary btn-sm" wire:click="record">{{ __('Record harvest') }}</button>
                </div>
            </div>
        </div>
    </div>
</div>
