<div>
    <div class="d-flex align-items-center gap-2 mb-4">
        <div class="flex-grow-1">
            <h4 class="mb-1">{{ __('Fee components') }}</h4>
            <p class="text-body-secondary mb-0">{{ __('The catalogue every fee structure item is built from.') }}</p>
        </div>
        <button type="button" class="btn btn-primary" wire:click="openCreateModal">
            <i class="ri ri-add-line me-1"></i>{{ __('New component') }}
        </button>
    </div>

    <x-data-table
        :columns="$this->tableColumns()"
        :rows="$components"
        :search="$search"
        :sort-column="$sortColumn"
        :sort-direction="$sortDirection"
        :per-page="$perPage"
        :column-filters="$columnFilters"
        :hidden-columns="$hiddenColumns"
        :per-page-options="$this->perPageOptions()"
        :with-actions="false"
    >
        @forelse ($components as $component)
            <tr wire:key="component-{{ $component->id }}">
                @if ($this->columnVisible('code'))
                    <td>{{ $component->code }}</td>
                @endif
                @if ($this->columnVisible('name'))
                    <td>
                        {{ $component->name }}
                        <div class="text-body-secondary small">{{ __('Income') }}: {{ $component->incomeAccount->code }} · {{ __('Debtor') }}: {{ $component->debtorAccount->code }}</div>
                    </td>
                @endif
                @if ($this->columnVisible('category'))
                    <td>{{ \Illuminate\Support\Str::headline($component->category) }}</td>
                @endif
                @if ($this->columnVisible('default_currency'))
                    <td>{{ $component->default_currency }}</td>
                @endif
                @if ($this->columnVisible('is_mandatory'))
                    <td>{{ $component->is_mandatory ? __('Yes') : __('No') }}</td>
                @endif
                @if ($this->columnVisible('is_active'))
                    <td>
                        <span class="badge {{ $component->is_active ? 'text-bg-success' : 'text-bg-secondary' }}">
                            {{ $component->is_active ? __('Active') : __('Inactive') }}
                        </span>
                    </td>
                @endif
            </tr>
        @empty
            <tr>
                <td colspan="6" class="text-center text-body-secondary py-4">{{ __('No fee components defined yet.') }}</td>
            </tr>
        @endforelse
    </x-data-table>

    @if ($showCreateModal)
        <div class="modal show d-block" tabindex="-1" style="background: rgba(0, 0, 0, .5);">
            <div class="modal-dialog modal-dialog-centered modal-lg">
                <div class="modal-content">
                    <form wire:submit="create">
                        <div class="modal-header">
                            <h5 class="modal-title">{{ __('New fee component') }}</h5>
                            <button type="button" class="btn-close" wire:click="$set('showCreateModal', false)" aria-label="{{ __('Close') }}"></button>
                        </div>
                        <div class="modal-body">
                            <div class="row g-3">
                                <div class="col-md-4">
                                    <div class="form-floating form-floating-outline">
                                        <input type="text" class="form-control @error('code') is-invalid @enderror" id="fc-code" wire:model="code" placeholder=" ">
                                        <label for="fc-code">{{ __('Code') }}</label>
                                        @error('code') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                    </div>
                                </div>
                                <div class="col-md-8">
                                    <div class="form-floating form-floating-outline">
                                        <input type="text" class="form-control @error('name') is-invalid @enderror" id="fc-name" wire:model="name" placeholder=" ">
                                        <label for="fc-name">{{ __('Name') }}</label>
                                        @error('name') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-floating form-floating-outline">
                                        <select class="form-select @error('category') is-invalid @enderror" id="fc-category" wire:model="category">
                                            @foreach (['tuition', 'levy', 'boarding', 'transport', 'activity', 'examination', 'material', 'deposit', 'other'] as $option)
                                                <option value="{{ $option }}">{{ \Illuminate\Support\Str::headline($option) }}</option>
                                            @endforeach
                                        </select>
                                        <label for="fc-category">{{ __('Category') }}</label>
                                        @error('category') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-floating form-floating-outline">
                                        <select class="form-select @error('defaultCurrency') is-invalid @enderror" id="fc-currency" wire:model="defaultCurrency">
                                            <option value="USD">USD</option>
                                            <option value="ZWG">ZWG</option>
                                        </select>
                                        <label for="fc-currency">{{ __('Default currency') }}</label>
                                        @error('defaultCurrency') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-floating form-floating-outline">
                                        <select class="form-select @error('taxCategory') is-invalid @enderror" id="fc-tax" wire:model="taxCategory">
                                            <option value="exempt">{{ __('Exempt') }}</option>
                                            <option value="zero">{{ __('Zero-rated') }}</option>
                                            <option value="standard">{{ __('Standard') }}</option>
                                        </select>
                                        <label for="fc-tax">{{ __('Tax category') }}</label>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-floating form-floating-outline">
                                        <select class="form-select @error('incomeAccountId') is-invalid @enderror" id="fc-income" wire:model="incomeAccountId">
                                            <option value="">{{ __('Select an account') }}</option>
                                            @foreach ($accounts as $account)
                                                <option value="{{ $account->id }}">{{ $account->code }} — {{ $account->name }}</option>
                                            @endforeach
                                        </select>
                                        <label for="fc-income">{{ __('Income account') }}</label>
                                        @error('incomeAccountId') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-floating form-floating-outline">
                                        <select class="form-select @error('debtorAccountId') is-invalid @enderror" id="fc-debtor" wire:model="debtorAccountId">
                                            <option value="">{{ __('Select an account') }}</option>
                                            @foreach ($accounts as $account)
                                                <option value="{{ $account->id }}">{{ $account->code }} — {{ $account->name }}</option>
                                            @endforeach
                                        </select>
                                        <label for="fc-debtor">{{ __('Debtor account') }}</label>
                                        @error('debtorAccountId') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-floating form-floating-outline">
                                        <select class="form-select" id="fc-cost-centre" wire:model="costCentreId">
                                            <option value="">{{ __('None') }}</option>
                                            @foreach ($costCentres as $costCentre)
                                                <option value="{{ $costCentre->id }}">{{ $costCentre->code }} — {{ $costCentre->name }}</option>
                                            @endforeach
                                        </select>
                                        <label for="fc-cost-centre">{{ __('Cost centre (optional)') }}</label>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-floating form-floating-outline">
                                        <input type="number" min="1" class="form-control @error('allocationPriority') is-invalid @enderror" id="fc-priority" wire:model="allocationPriority" placeholder=" ">
                                        <label for="fc-priority">{{ __('Allocation priority (lower settles first)') }}</label>
                                    </div>
                                </div>
                                <div class="col-12 d-flex flex-wrap gap-4">
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" role="switch" id="fc-mandatory" wire:model="isMandatory">
                                        <label class="form-check-label" for="fc-mandatory">{{ __('Mandatory') }}</label>
                                    </div>
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" role="switch" id="fc-refundable" wire:model="isRefundable">
                                        <label class="form-check-label" for="fc-refundable">{{ __('Refundable') }}</label>
                                    </div>
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" role="switch" id="fc-fiscalisable" wire:model="isFiscalisable">
                                        <label class="form-check-label" for="fc-fiscalisable">{{ __('Fiscalisable') }}</label>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-outline-secondary" wire:click="$set('showCreateModal', false)">{{ __('Cancel') }}</button>
                            <button type="submit" class="btn btn-primary" wire:loading.attr="disabled">{{ __('Create component') }}</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endif
</div>
