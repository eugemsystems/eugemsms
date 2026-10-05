<div>
    <h4 class="mb-1">{{ __('Route costing') }}</h4>
    <p class="text-body-secondary mb-4">{{ __('Fuel, maintenance, compliance and depreciation against fee income — does the route pay for itself?') }}</p>

    <div class="row g-2 mb-3">
        <div class="col-md-4">
            <select class="form-select" wire:model.live="routeId">
                <option value="">{{ __('Route') }}</option>
                @foreach ($routes as $route)
                    <option value="{{ $route->id }}">{{ $route->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-3"><input type="date" class="form-control" wire:model.live="periodStart"></div>
        <div class="col-md-3"><input type="date" class="form-control" wire:model.live="periodEnd"></div>
    </div>

    @if ($result)
        <div class="card" style="max-width: 30rem">
            <ul class="list-group list-group-flush">
                <li class="list-group-item d-flex justify-content-between"><span>{{ __('Fuel') }}</span><span>{{ number_format($result->fuelCostMinor / 100, 2) }}</span></li>
                <li class="list-group-item d-flex justify-content-between"><span>{{ __('Maintenance') }}</span><span>{{ number_format($result->maintenanceCostMinor / 100, 2) }}</span></li>
                <li class="list-group-item d-flex justify-content-between"><span>{{ __('Compliance') }}</span><span>{{ number_format($result->complianceCostMinor / 100, 2) }}</span></li>
                <li class="list-group-item d-flex justify-content-between"><span>{{ __('Depreciation') }}</span><span>{{ number_format($result->depreciationMinor / 100, 2) }}</span></li>
                <li class="list-group-item d-flex justify-content-between fw-bold"><span>{{ __('Total cost') }}</span><span>{{ number_format($result->totalCostMinor() / 100, 2) }}</span></li>
                <li class="list-group-item d-flex justify-content-between"><span>{{ __('Fee income') }}</span><span>{{ number_format($result->feeIncomeMinor / 100, 2) }}</span></li>
                <li class="list-group-item d-flex justify-content-between fw-bold {{ $result->marginMinor() >= 0 ? 'text-success' : 'text-danger' }}"><span>{{ __('Margin') }}</span><span>{{ number_format($result->marginMinor() / 100, 2) }}</span></li>
            </ul>
        </div>
    @else
        <p class="text-body-secondary">{{ __('Select a route to see its costing.') }}</p>
    @endif
</div>
