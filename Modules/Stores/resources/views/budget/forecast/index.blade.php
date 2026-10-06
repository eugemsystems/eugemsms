<div>
    <h4 class="mb-1">{{ __('Forecasts') }}</h4>
    <p class="text-body-secondary mb-4">{{ __('A scenario never alters the approved budget — it is clearly labelled as a scenario.') }}</p>

    <div class="row g-4">
        <div class="col-lg-5">
            <div class="card">
                <div class="card-header">{{ __('New scenario') }}</div>
                <div class="card-body">
                    <select class="form-select mb-2" wire:model="forecastType">
                        <option value="fee_income">{{ __('Fee income') }}</option>
                        <option value="cash_flow">{{ __('Cash flow') }}</option>
                        <option value="enrolment">{{ __('Enrolment') }}</option>
                        <option value="expenditure">{{ __('Expenditure') }}</option>
                    </select>
                    @if (in_array($forecastType, ['fee_income', 'cash_flow'], true))
                        <div class="border rounded p-2 mb-2">
                            <select class="form-select form-select-sm mb-2" wire:model="referenceYearId">
                                <option value="">{{ __('Reference academic year') }}</option>
                                @foreach ($years as $year)
                                    <option value="{{ $year->id }}">{{ $year->name }}</option>
                                @endforeach
                            </select>
                            @error('referenceYearId') <div class="text-danger small">{{ $message }}</div> @enderror
                            <div class="row g-2 mb-2">
                                <div class="col"><input type="number" step="any" class="form-control form-control-sm" wire:model="enrolmentGrowthPercent" placeholder="{{ __('Enrolment growth %') }}"></div>
                                <div class="col"><input type="number" step="any" class="form-control form-control-sm" wire:model="feeIncreasePercent" placeholder="{{ __('Fee increase %') }}"></div>
                                <div class="col"><input type="number" step="any" class="form-control form-control-sm" wire:model="collectionRateOverride" placeholder="{{ __('Collection % (blank = history)') }}"></div>
                            </div>
                            <button type="button" class="btn btn-outline-primary btn-sm" wire:click="compute">{{ __('Compute from actuals') }}</button>
                        </div>
                    @endif
                    <input type="text" class="form-control mb-2" wire:model="scenarioName" placeholder="{{ __('Scenario name (e.g. \"Base\", \"Collection 85%\")') }}">
                    <textarea class="form-control mb-2" rows="3" wire:model="assumptionsJson" placeholder="{{ __('Assumptions (JSON)') }}"></textarea>
                    <textarea class="form-control mb-2" rows="3" wire:model="projectionsJson" placeholder="{{ __('Projections (JSON)') }}"></textarea>
                    <div class="form-check mb-2">
                        <input type="checkbox" class="form-check-input" id="isBaseline" wire:model="isBaseline">
                        <label class="form-check-label" for="isBaseline">{{ __('Mark as baseline') }}</label>
                    </div>
                    <button type="button" class="btn btn-primary btn-sm" wire:click="create">{{ __('Save') }}</button>
                </div>
            </div>
        </div>
        <div class="col-lg-7">
            <div class="card">
                <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        <thead><tr><th>{{ __('Type') }}</th><th>{{ __('Scenario') }}</th><th>{{ __('Baseline') }}</th><th>{{ __('Generated') }}</th></tr></thead>
                        <tbody>
                            @forelse ($forecasts as $forecast)
                                <tr wire:key="forecast-{{ $forecast->id }}">
                                    <td>{{ $forecast->forecast_type }}</td>
                                    <td>{{ $forecast->scenario_name }}</td>
                                    <td>{{ $forecast->is_baseline ? '⭐' : '' }}</td>
                                    <td>{{ $forecast->generated_at->format('Y-m-d') }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="4" class="text-center text-body-secondary py-3">{{ __('No forecasts yet.') }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
