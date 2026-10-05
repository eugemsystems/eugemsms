<div>
    <h4 class="mb-1">{{ __('Daily production') }}</h4>

    <div class="row g-4">
        <div class="col-lg-7">
            <div class="card">
                <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        <thead><tr><th>{{ __('Unit') }}</th><th>{{ __('Date') }}</th><th>{{ __('Type') }}</th><th>{{ __('Quantity') }}</th><th>{{ __('Destination') }}</th></tr></thead>
                        <tbody>
                            @forelse ($outputs as $output)
                                <tr wire:key="output-{{ $output->id }}">
                                    <td>{{ $output->productionUnit->name }}</td>
                                    <td>{{ $output->output_date->toFormattedDateString() }}</td>
                                    <td>{{ $output->output_type }}</td>
                                    <td>{{ $output->quantity }} {{ $output->unit }}</td>
                                    <td>{{ $output->destination }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="text-center text-body-secondary py-3">{{ __('No production recorded.') }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <div class="col-lg-5">
            <div class="card">
                <div class="card-header">{{ __('Record today\'s production') }}</div>
                <div class="card-body">
                    <select class="form-select mb-2" wire:model="productionUnitId">
                        <option value="">{{ __('Production unit') }}</option>
                        @foreach ($units as $unit)
                            <option value="{{ $unit->id }}">{{ $unit->name }}</option>
                        @endforeach
                    </select>
                    <select class="form-select mb-2" wire:model="outputType">
                        @foreach (['milk', 'eggs', 'meat', 'manure'] as $type)
                            <option value="{{ $type }}">{{ $type }}</option>
                        @endforeach
                    </select>
                    <input type="number" step="0.001" class="form-control mb-2" wire:model="quantity" placeholder="{{ __('Quantity') }}">
                    <input type="text" class="form-control mb-2" wire:model="unit" placeholder="{{ __('Unit (litres/dozen/kg)') }}">
                    <select class="form-select mb-2" wire:model="destination">
                        @foreach (['kitchen', 'store', 'sale', 'waste'] as $dest)
                            <option value="{{ $dest }}">{{ $dest }}</option>
                        @endforeach
                    </select>
                    <button type="button" class="btn btn-primary btn-sm" wire:click="record">{{ __('Record') }}</button>
                </div>
            </div>
        </div>
    </div>
</div>
