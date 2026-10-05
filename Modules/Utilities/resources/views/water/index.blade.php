<div>
    <h4 class="mb-1">{{ __('Water') }}</h4>

    <div class="row g-4">
        <div class="col-lg-7">
            <div class="card mb-3">
                <div class="card-header">{{ __('Sources') }}</div>
                <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        <thead><tr><th>{{ __('Code') }}</th><th>{{ __('Name') }}</th><th>{{ __('Type') }}</th><th>{{ __('Status') }}</th><th>{{ __('Quality') }}</th></tr></thead>
                        <tbody>
                            @forelse ($sources as $source)
                                <tr wire:key="source-{{ $source->id }}">
                                    <td>{{ $source->code }}</td>
                                    <td>{{ $source->name }}</td>
                                    <td>{{ $source->source_type }}</td>
                                    <td><span class="{{ $source->status === 'reduced_yield' ? 'badge bg-warning text-dark' : '' }}">{{ $source->status }}</span></td>
                                    <td>
                                        @if ($source->water_quality_status === 'not_potable')
                                            <span class="badge bg-danger">{{ __('Not potable') }}</span>
                                        @else
                                            {{ $source->water_quality_status ?? '—' }}
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="text-center text-body-secondary py-3">{{ __('No water sources.') }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="card">
                <div class="card-header">{{ __('Readings') }}</div>
                <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        <thead><tr><th>{{ __('Source') }}</th><th>{{ __('Date') }}</th><th>{{ __('Storage %') }}</th><th>{{ __('Yield') }}</th></tr></thead>
                        <tbody>
                            @forelse ($readings as $r)
                                <tr wire:key="wreading-{{ $r->id }}">
                                    <td>{{ $r->waterSource->code }}</td>
                                    <td>{{ $r->read_on->toDateString() }}</td>
                                    <td>{{ $r->storage_level_percent !== null ? number_format((float) $r->storage_level_percent, 1).'%' : '—' }}</td>
                                    <td>{{ $r->yield_observed !== null ? number_format((float) $r->yield_observed, 1) : '—' }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="4" class="text-center text-body-secondary py-3">{{ __('No readings.') }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <div class="col-lg-5">
            <div class="card mb-3">
                <div class="card-header">{{ __('New water source') }}</div>
                <div class="card-body">
                    <input type="text" class="form-control mb-2" wire:model="code" placeholder="{{ __('Code') }}">
                    <input type="text" class="form-control mb-2" wire:model="name" placeholder="{{ __('Name') }}">
                    <select class="form-select mb-2" wire:model="sourceType">
                        @foreach (['borehole', 'municipal', 'river', 'rainwater', 'tank'] as $type)
                            <option value="{{ $type }}">{{ $type }}</option>
                        @endforeach
                    </select>
                    <input type="number" step="0.01" class="form-control mb-2" wire:model="yieldLitresPerHour" placeholder="{{ __('Yield baseline (L/h, optional)') }}">
                    <input type="number" step="0.01" class="form-control mb-2" wire:model="storageCapacityLitres" placeholder="{{ __('Storage capacity (litres, optional)') }}">
                    <button type="button" class="btn btn-primary btn-sm" wire:click="createSource">{{ __('Register source') }}</button>
                </div>
            </div>
            <div class="card mb-3">
                <div class="card-header">{{ __('Record reading') }}</div>
                <div class="card-body">
                    <select class="form-select mb-2" wire:model="waterSourceId">
                        <option value="">{{ __('Source') }}</option>
                        @foreach ($sources as $source)
                            <option value="{{ $source->id }}">{{ $source->code }}</option>
                        @endforeach
                    </select>
                    <input type="date" class="form-control mb-2" wire:model="readOn">
                    <input type="number" step="0.01" class="form-control mb-2" wire:model="storageLevelPercent" placeholder="{{ __('Storage level % (optional)') }}">
                    <input type="number" step="0.01" class="form-control mb-2" wire:model="volumePumpedLitres" placeholder="{{ __('Volume pumped (litres, optional)') }}">
                    <input type="number" step="0.01" class="form-control mb-2" wire:model="yieldObserved" placeholder="{{ __('Observed yield (L/h, optional)') }}">
                    <select class="form-select mb-2" wire:model="costCentreId">
                        <option value="">{{ __('Cost centre (optional)') }}</option>
                        @foreach ($costCentres as $cc)
                            <option value="{{ $cc->id }}">{{ $cc->code }}</option>
                        @endforeach
                    </select>
                    <button type="button" class="btn btn-primary btn-sm" wire:click="recordReading">{{ __('Record reading') }}</button>
                </div>
            </div>
            <div class="card">
                <div class="card-header">{{ __('Record quality test') }}</div>
                <div class="card-body">
                    <select class="form-select mb-2" wire:model="qualitySourceId">
                        <option value="">{{ __('Source') }}</option>
                        @foreach ($sources as $source)
                            <option value="{{ $source->id }}">{{ $source->code }}</option>
                        @endforeach
                    </select>
                    <select class="form-select mb-2" wire:model="qualityStatus">
                        <option value="potable">{{ __('Potable') }}</option>
                        <option value="treatment_required">{{ __('Treatment required') }}</option>
                        <option value="not_potable">{{ __('Not potable') }}</option>
                    </select>
                    <button type="button" class="btn btn-danger btn-sm" wire:click="recordQualityTest">{{ __('Record test result') }}</button>
                </div>
            </div>
        </div>
    </div>
</div>
