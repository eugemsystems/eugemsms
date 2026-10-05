<div>
    <h4 class="mb-1">{{ __('Energy dashboard') }}</h4>

    <div class="row g-2 mb-3 align-items-end" style="max-width:30rem">
        <div class="col-5"><input type="date" class="form-control" wire:model="periodStart"></div>
        <div class="col-5"><input type="date" class="form-control" wire:model="periodEnd"></div>
        <div class="col-2"><button type="button" class="btn btn-primary btn-sm w-100" wire:click="compute">{{ __('Go') }}</button></div>
    </div>

    @if ($hasResult)
        <div class="row g-3 mb-3">
            <div class="col-md-4">
                <div class="card"><div class="card-body">
                    <div class="text-body-secondary small">{{ __('Grid') }}</div>
                    <div class="fs-5">{{ number_format($gridKwh, 1) }} kWh</div>
                    <div class="small">{{ number_format($gridCostMinor / 100, 2) }} {{ $currency }}</div>
                </div></div>
            </div>
            <div class="col-md-4">
                <div class="card"><div class="card-body">
                    <div class="text-body-secondary small">{{ __('Generator') }}</div>
                    <div class="fs-5">{{ number_format($generatorKwh, 1) }} kWh</div>
                    <div class="small">{{ number_format($generatorCostMinor / 100, 2) }} {{ $currency }}</div>
                </div></div>
            </div>
            <div class="col-md-4">
                <div class="card"><div class="card-body">
                    <div class="text-body-secondary small">{{ __('Outage hours') }}</div>
                    <div class="fs-5">{{ number_format($outageHours, 1) }}</div>
                </div></div>
            </div>
        </div>

        <div class="alert alert-info">
            {{ __('Load shedding cost the school') }}
            <strong>{{ number_format($additionalCostMinor / 100, 2) }} {{ $currency }}</strong>
            {{ __('this period in additional generation, over') }}
            <strong>{{ number_format($outageHours, 1) }}</strong> {{ __('hours of outage.') }}
        </div>
    @endif
</div>
