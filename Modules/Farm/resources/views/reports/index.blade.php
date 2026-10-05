<div>
    <h4 class="mb-1">{{ __('Farm reports') }}</h4>

    <ul class="nav nav-tabs mb-3">
        <li class="nav-item"><a href="javascript:void(0)" class="nav-link {{ $tab === 'profitability' ? 'active' : '' }}" wire:click="$set('tab', 'profitability')">{{ __('Profitability') }} ⭐</a></li>
        <li class="nav-item"><a href="javascript:void(0)" class="nav-link {{ $tab === 'savings' ? 'active' : '' }}" wire:click="$set('tab', 'savings')">{{ __('Savings') }}</a></li>
    </ul>

    <div class="row g-2 mb-3">
        <div class="col-md-4"><input type="date" class="form-control" wire:model.live="periodStart"></div>
        <div class="col-md-4"><input type="date" class="form-control" wire:model.live="periodEnd"></div>
    </div>

    @if ($tab === 'profitability')
        <div class="row g-2 mb-3">
            <div class="col-md-4">
                <select class="form-select" wire:model.live="productionUnitId">
                    <option value="">{{ __('Production unit') }}</option>
                    @foreach ($units as $unit)
                        <option value="{{ $unit->id }}">{{ $unit->name }}</option>
                    @endforeach
                </select>
            </div>
        </div>
        @if ($profitability)
            <div class="card" style="max-width:30rem">
                <ul class="list-group list-group-flush">
                    <li class="list-group-item d-flex justify-content-between"><span>{{ __('Total crop cost') }}</span><span>{{ number_format($profitability->totalCropCostMinor / 100, 2) }}</span></li>
                    <li class="list-group-item d-flex justify-content-between"><span>{{ __('Kitchen transfer value') }}</span><span>{{ number_format($profitability->kitchenTransferValueMinor / 100, 2) }}</span></li>
                    <li class="list-group-item d-flex justify-content-between"><span>{{ __('External sales') }}</span><span>{{ number_format($profitability->externalSalesMinor / 100, 2) }}</span></li>
                    <li class="list-group-item d-flex justify-content-between fw-bold {{ $profitability->netPositionMinor() >= 0 ? 'text-success' : 'text-danger' }}"><span>{{ __('Net position') }}</span><span>{{ number_format($profitability->netPositionMinor() / 100, 2) }}</span></li>
                </ul>
            </div>
        @else
            <p class="text-body-secondary">{{ __('Select a production unit.') }}</p>
        @endif
    @else
        <div class="card" style="max-width:30rem">
            <ul class="list-group list-group-flush">
                <li class="list-group-item d-flex justify-content-between"><span>{{ __('Market value') }}</span><span>{{ number_format($savings->marketValueMinor / 100, 2) }}</span></li>
                <li class="list-group-item d-flex justify-content-between"><span>{{ __('Internal cost') }}</span><span>{{ number_format($savings->internalCostMinor / 100, 2) }}</span></li>
                <li class="list-group-item d-flex justify-content-between fw-bold text-success"><span>{{ __('Saved') }}</span><span>{{ number_format($savings->savingsMinor() / 100, 2) }}</span></li>
            </ul>
        </div>
    @endif
</div>
