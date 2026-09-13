<div>
    <div class="d-flex align-items-center gap-2 mb-4">
        <div class="flex-grow-1">
            <h4 class="mb-1">{{ __('Posting rules') }}</h4>
            <p class="text-body-secondary mb-0">{{ __('Which accounts each financial event posts to.') }}</p>
        </div>
        <button type="button" class="btn btn-primary" wire:click="openEditModal">
            <i class="ri ri-add-line me-1"></i>{{ __('New posting rule') }}
        </button>
    </div>

    <x-data-table
        :columns="$this->tableColumns()"
        :rows="$rules"
        :search="$search"
        :sort-column="$sortColumn"
        :sort-direction="$sortDirection"
        :per-page="$perPage"
        :column-filters="$columnFilters"
        :hidden-columns="$hiddenColumns"
        :per-page-options="$this->perPageOptions()"
    >
        @forelse ($rules as $rule)
            <tr wire:key="rule-{{ $rule->id }}">
                @if ($this->columnVisible('event_key'))
                    <td>
                        <code>{{ $rule->event_key }}</code>
                        <div class="text-body-secondary small">
                            {{ __('Dr') }} {{ $rule->debitAccount?->code ?? $rule->debit_resolver ?? '—' }}
                            / {{ __('Cr') }} {{ $rule->creditAccount?->code ?? $rule->credit_resolver ?? '—' }}
                        </div>
                    </td>
                @endif
                @if ($this->columnVisible('is_active'))
                    <td>
                        <span class="badge {{ $rule->is_active ? 'text-bg-success' : 'text-bg-secondary' }}">
                            {{ $rule->is_active ? __('Active') : __('Inactive') }}
                        </span>
                    </td>
                @endif
                <td class="text-end">
                    <button type="button" class="btn btn-icon btn-sm btn-outline-secondary" wire:click="openEditModal({{ $rule->id }})" title="{{ __('Edit') }}" aria-label="{{ __('Edit') }}">
                        <i class="icon-base ri ri-pencil-line icon-22px"></i>
                    </button>
                </td>
            </tr>
        @empty
            <tr>
                <td colspan="3" class="text-center text-body-secondary py-4">{{ __('No posting rules defined yet.') }}</td>
            </tr>
        @endforelse
    </x-data-table>

    @if ($showEditModal)
        <div class="modal show d-block" tabindex="-1" style="background: rgba(0, 0, 0, .5);">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <form wire:submit="save">
                        <div class="modal-header">
                            <h5 class="modal-title">{{ $editingRuleId !== null ? __('Edit posting rule') : __('New posting rule') }}</h5>
                            <button type="button" class="btn-close" wire:click="$set('showEditModal', false)" aria-label="{{ __('Close') }}"></button>
                        </div>
                        <div class="modal-body">
                            <div class="mb-3">
                                <div class="form-floating form-floating-outline">
                                    <input type="text" class="form-control @error('eventKey') is-invalid @enderror" id="eventKey" wire:model="eventKey" placeholder=" " @disabled($editingRuleId !== null)>
                                    <label for="eventKey">{{ __('Event key') }}</label>
                                    @error('eventKey') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                </div>
                            </div>
                            <div class="row g-3 mb-3">
                                <div class="col-6">
                                    <div class="form-floating form-floating-outline">
                                        <select class="form-select" id="debitAccountId" wire:model="debitAccountId">
                                            <option value="">{{ __('None') }}</option>
                                            @foreach ($accounts as $account)
                                                <option value="{{ $account->id }}">{{ $account->code }} — {{ $account->name }}</option>
                                            @endforeach
                                        </select>
                                        <label for="debitAccountId">{{ __('Debit account') }}</label>
                                    </div>
                                </div>
                                <div class="col-6">
                                    <div class="form-floating form-floating-outline">
                                        <select class="form-select" id="creditAccountId" wire:model="creditAccountId">
                                            <option value="">{{ __('None') }}</option>
                                            @foreach ($accounts as $account)
                                                <option value="{{ $account->id }}">{{ $account->code }} — {{ $account->name }}</option>
                                            @endforeach
                                        </select>
                                        <label for="creditAccountId">{{ __('Credit account') }}</label>
                                    </div>
                                </div>
                            </div>
                            <div class="mb-3">
                                <div class="form-floating form-floating-outline">
                                    <select class="form-select" id="costCentreId" wire:model="costCentreId">
                                        <option value="">{{ __('None') }}</option>
                                        @foreach ($costCentres as $costCentre)
                                            <option value="{{ $costCentre->id }}">{{ $costCentre->code }} — {{ $costCentre->name }}</option>
                                        @endforeach
                                    </select>
                                    <label for="costCentreId">{{ __('Cost centre (optional)') }}</label>
                                </div>
                            </div>
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" role="switch" id="isActive" wire:model="isActive">
                                <label class="form-check-label" for="isActive">{{ __('Active') }}</label>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-outline-secondary" wire:click="$set('showEditModal', false)">{{ __('Cancel') }}</button>
                            <button type="submit" class="btn btn-primary" wire:loading.attr="disabled">{{ __('Save') }}</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endif
</div>
