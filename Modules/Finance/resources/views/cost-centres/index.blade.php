<div>
    <div class="d-flex align-items-center gap-2 mb-4">
        <div class="flex-grow-1">
            <h4 class="mb-1">{{ __('Cost centres') }}</h4>
            <p class="text-body-secondary mb-0">{{ __(':school\'s cost centres.', ['school' => $school->name]) }}</p>
        </div>
        <button type="button" class="btn btn-primary" wire:click="openCreateModal">
            <i class="ri ri-add-line me-1"></i>{{ __('New cost centre') }}
        </button>
    </div>

    <x-data-table
        :columns="$this->tableColumns()"
        :rows="$costCentres"
        :search="$search"
        :sort-column="$sortColumn"
        :sort-direction="$sortDirection"
        :per-page="$perPage"
        :column-filters="$columnFilters"
        :hidden-columns="$hiddenColumns"
        :per-page-options="$this->perPageOptions()"
        :with-actions="false"
    >
        @forelse ($costCentres as $costCentre)
            <tr wire:key="cost-centre-{{ $costCentre->id }}">
                @if ($this->columnVisible('code'))
                    <td>{{ $costCentre->code }}</td>
                @endif
                @if ($this->columnVisible('name'))
                    <td>
                        {{ $costCentre->name }}
                        @if ($costCentre->parent)
                            <div class="text-body-secondary small">{{ __('Under :parent', ['parent' => $costCentre->parent->name]) }}</div>
                        @endif
                    </td>
                @endif
                @if ($this->columnVisible('is_profit_centre'))
                    <td>{{ $costCentre->is_profit_centre ? __('Yes') : __('No') }}</td>
                @endif
                @if ($this->columnVisible('is_active'))
                    <td>
                        <span class="badge {{ $costCentre->is_active ? 'text-bg-success' : 'text-bg-secondary' }}">
                            {{ $costCentre->is_active ? __('Active') : __('Inactive') }}
                        </span>
                    </td>
                @endif
            </tr>
        @empty
            <tr>
                <td colspan="4" class="text-center text-body-secondary py-4">{{ __('No cost centres defined yet.') }}</td>
            </tr>
        @endforelse
    </x-data-table>

    @if ($showCreateModal)
        <div class="modal show d-block" tabindex="-1" style="background: rgba(0, 0, 0, .5);">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <form wire:submit="create">
                        <div class="modal-header">
                            <h5 class="modal-title">{{ __('New cost centre') }}</h5>
                            <button type="button" class="btn-close" wire:click="$set('showCreateModal', false)" aria-label="{{ __('Close') }}"></button>
                        </div>
                        <div class="modal-body">
                            <div class="row g-3">
                                <div class="col-6">
                                    <div class="form-floating form-floating-outline">
                                        <input type="text" class="form-control @error('code') is-invalid @enderror" id="cc-code" wire:model="code" placeholder=" ">
                                        <label for="cc-code">{{ __('Code') }}</label>
                                        @error('code') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                    </div>
                                </div>
                                <div class="col-6">
                                    <div class="form-floating form-floating-outline">
                                        <input type="text" class="form-control @error('name') is-invalid @enderror" id="cc-name" wire:model="name" placeholder=" ">
                                        <label for="cc-name">{{ __('Name') }}</label>
                                        @error('name') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                    </div>
                                </div>
                                <div class="col-6">
                                    <div class="form-floating form-floating-outline">
                                        <select class="form-select" id="cc-parent" wire:model="parentId">
                                            <option value="">{{ __('No parent') }}</option>
                                            @foreach ($parentCandidates as $parent)
                                                <option value="{{ $parent->id }}">{{ $parent->code }} — {{ $parent->name }}</option>
                                            @endforeach
                                        </select>
                                        <label for="cc-parent">{{ __('Parent (optional)') }}</label>
                                    </div>
                                </div>
                                <div class="col-6">
                                    <div class="form-floating form-floating-outline">
                                        <select class="form-select" id="cc-section" wire:model="sectionId">
                                            <option value="">{{ __('Whole school') }}</option>
                                            @foreach ($sections as $section)
                                                <option value="{{ $section->id }}">{{ $section->name }}</option>
                                            @endforeach
                                        </select>
                                        <label for="cc-section">{{ __('Section (optional)') }}</label>
                                    </div>
                                </div>
                                <div class="col-12">
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" role="switch" id="cc-profit" wire:model="isProfitCentre">
                                        <label class="form-check-label" for="cc-profit">{{ __('Profit centre') }}</label>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-outline-secondary" wire:click="$set('showCreateModal', false)">{{ __('Cancel') }}</button>
                            <button type="submit" class="btn btn-primary" wire:loading.attr="disabled">{{ __('Create cost centre') }}</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endif
</div>
