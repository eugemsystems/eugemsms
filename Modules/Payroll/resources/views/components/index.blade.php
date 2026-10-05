<div>
    <h4 class="mb-3">{{ __('Pay components') }}</h4>

    <div class="row g-4">
        <div class="col-lg-7">
            <div class="card">
                <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        <thead><tr><th>{{ __('Code') }}</th><th>{{ __('Name') }}</th><th>{{ __('Type') }}</th><th>{{ __('Method') }}</th><th>{{ __('Tax treatment') }}</th></tr></thead>
                        <tbody>
                            @forelse ($components as $component)
                                <tr wire:key="component-{{ $component->id }}">
                                    <td>{{ $component->code }}</td>
                                    <td>{{ $component->name }}</td>
                                    <td>{{ $component->component_type }}</td>
                                    <td>{{ $component->calculation_method }}</td>
                                    <td class="small">
                                        @if ($component->is_taxable) <span class="badge bg-light text-dark border">{{ __('taxable') }}</span> @endif
                                        @if ($component->is_pensionable) <span class="badge bg-light text-dark border">{{ __('pensionable') }}</span> @endif
                                        @if ($component->is_nec_applicable) <span class="badge bg-light text-dark border">{{ __('NEC') }}</span> @endif
                                        @if ($component->is_zimdef_applicable) <span class="badge bg-light text-dark border">{{ __('ZIMDEF') }}</span> @endif
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="text-center text-body-secondary py-3">{{ __('No pay components yet.') }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <div class="col-lg-5">
            <div class="card">
                <div class="card-header">{{ __('New pay component') }}</div>
                <div class="card-body">
                    <input type="text" class="form-control mb-2" wire:model="code" placeholder="{{ __('Code (e.g. HOUSING)') }}">
                    <input type="text" class="form-control mb-2" wire:model="name" placeholder="{{ __('Name') }}">
                    <select class="form-select mb-2" wire:model="componentType">
                        <option value="earning">{{ __('Earning') }}</option>
                        <option value="deduction">{{ __('Deduction') }}</option>
                        <option value="employer_contribution">{{ __('Employer contribution') }}</option>
                    </select>
                    <select class="form-select mb-2" wire:model="category">
                        <option value="basic">{{ __('Basic') }}</option>
                        <option value="allowance">{{ __('Allowance') }}</option>
                        <option value="overtime">{{ __('Overtime') }}</option>
                        <option value="bonus">{{ __('Bonus') }}</option>
                        <option value="statutory">{{ __('Statutory') }}</option>
                        <option value="loan">{{ __('Loan') }}</option>
                        <option value="third_party">{{ __('Third party') }}</option>
                        <option value="benefit_in_kind">{{ __('Benefit in kind') }}</option>
                    </select>
                    <select class="form-select mb-2" wire:model="calculationMethod">
                        <option value="fixed">{{ __('Fixed amount') }}</option>
                        <option value="percentage_of_basic">{{ __('Percentage of basic') }}</option>
                    </select>
                    <input type="number" class="form-control mb-2" wire:model="defaultAmountMinor" placeholder="{{ __('Default amount (minor units)') }}">
                    <input type="text" class="form-control mb-2" wire:model="defaultPercent" placeholder="{{ __('Default percent (e.g. 10.000)') }}">
                    <select class="form-select mb-2" wire:model="currency">
                        <option value="">{{ __("Staff member's own currency") }}</option>
                        <option value="USD">USD</option>
                        <option value="ZWG">ZWG</option>
                    </select>
                    <div class="form-check"><input class="form-check-input" type="checkbox" wire:model="isTaxable" id="isTaxable"><label class="form-check-label" for="isTaxable">{{ __('Taxable') }}</label></div>
                    <div class="form-check"><input class="form-check-input" type="checkbox" wire:model="isPensionable" id="isPensionable"><label class="form-check-label" for="isPensionable">{{ __('Pensionable (NSSA base)') }}</label></div>
                    <div class="form-check"><input class="form-check-input" type="checkbox" wire:model="isNecApplicable" id="isNecApplicable"><label class="form-check-label" for="isNecApplicable">{{ __('NEC applicable') }}</label></div>
                    <div class="form-check mb-2"><input class="form-check-input" type="checkbox" wire:model="isZimdefApplicable" id="isZimdefApplicable"><label class="form-check-label" for="isZimdefApplicable">{{ __('ZIMDEF applicable') }}</label></div>
                    <input type="text" class="form-control mb-2" wire:model="taxablePercent" placeholder="{{ __('Taxable percent (default 100)') }}">
                    <select class="form-select mb-2" wire:model="expenseAccountId">
                        <option value="">{{ __('Expense account (optional)') }}</option>
                        @foreach ($accounts as $account)
                            <option value="{{ $account->id }}">{{ $account->code }} — {{ $account->name }}</option>
                        @endforeach
                    </select>
                    <select class="form-select mb-2" wire:model="liabilityAccountId">
                        <option value="">{{ __('Liability account (optional)') }}</option>
                        @foreach ($accounts as $account)
                            <option value="{{ $account->id }}">{{ $account->code }} — {{ $account->name }}</option>
                        @endforeach
                    </select>
                    <button type="button" class="btn btn-primary btn-sm" wire:click="create">{{ __('Create component') }}</button>
                </div>
            </div>
        </div>
    </div>
</div>
