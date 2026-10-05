<div>
    <h4 class="mb-1">{{ __('Fiscalisation rules') }}</h4>
    <p class="text-body-secondary small">{{ __('Tuition and exempt levies are not fiscalised by default. Tuckshop, uniform, textbook, hall hire, bus hire and farm produce sales are — every default is a rule row a school can change with its accountant.') }}</p>

    <div class="row g-4">
        <div class="col-lg-7">
            <div class="card">
                <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        <thead><tr><th>{{ __('Rule') }}</th><th>{{ __('Source') }}</th><th>{{ __('Fiscalisable') }}</th><th>{{ __('Tax type') }}</th><th>{{ __('Rate') }}</th><th>{{ __('Priority') }}</th></tr></thead>
                        <tbody>
                            @forelse ($rules as $rule)
                                <tr wire:key="rule-{{ $rule->id }}">
                                    <td>{{ $rule->rule_name }}</td>
                                    <td>{{ $rule->source_type }}{{ $rule->source_identifier ? ' / '.$rule->source_identifier : '' }}</td>
                                    <td><span class="badge {{ $rule->is_fiscalisable ? 'bg-success' : 'bg-secondary' }}">{{ $rule->is_fiscalisable ? __('yes') : __('no') }}</span></td>
                                    <td>{{ $rule->tax_type }}</td>
                                    <td>{{ $rule->tax_rate_percent }}%</td>
                                    <td>{{ $rule->priority }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="6" class="text-center text-body-secondary py-3">{{ __('No fiscalisation rules yet.') }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <div class="col-lg-5">
            <div class="card">
                <div class="card-header">{{ __('New rule') }}</div>
                <div class="card-body">
                    <input type="text" class="form-control mb-2" wire:model="ruleName" placeholder="{{ __('Rule name') }}">
                    <select class="form-select mb-2" wire:model="sourceType">
                        <option value="fee_component">{{ __('Fee component') }}</option>
                        <option value="wallet_product">{{ __('Wallet product') }}</option>
                        <option value="farm_sale">{{ __('Farm sale') }}</option>
                        <option value="facility_hire">{{ __('Facility hire') }}</option>
                        <option value="uniform_sale">{{ __('Uniform sale') }}</option>
                    </select>
                    <input type="text" class="form-control mb-2" wire:model="sourceIdentifier" placeholder="{{ __('Source identifier (component code/category, optional)') }}">
                    <div class="form-check mb-2"><input class="form-check-input" type="checkbox" wire:model="isFiscalisable" id="isFiscalisable"><label class="form-check-label" for="isFiscalisable">{{ __('Is fiscalisable') }}</label></div>
                    <select class="form-select mb-2" wire:model="taxType">
                        <option value="standard">{{ __('Standard') }}</option>
                        <option value="zero_rated">{{ __('Zero rated') }}</option>
                        <option value="exempt">{{ __('Exempt') }}</option>
                        <option value="withholding">{{ __('Withholding') }}</option>
                    </select>
                    <input type="text" class="form-control mb-2" wire:model="taxRatePercent" placeholder="{{ __('Tax rate percent') }}">
                    <input type="text" class="form-control mb-2" wire:model="taxCode" placeholder="{{ __('FDMS tax code (optional)') }}">
                    <input type="number" class="form-control mb-2" wire:model="priority" placeholder="{{ __('Priority') }}">
                    <textarea class="form-control mb-2" wire:model="rationale" rows="2" placeholder="{{ __('Rationale (required, for audit)') }}"></textarea>
                    <button type="button" class="btn btn-primary btn-sm" wire:click="create">{{ __('Create & review') }}</button>
                </div>
            </div>
        </div>
    </div>
</div>
