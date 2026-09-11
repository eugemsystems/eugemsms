<div>
    <div class="d-flex align-items-center justify-content-between mb-4">
        <div>
            <h4 class="mb-1">{{ __('Schools') }}</h4>
            <p class="text-body-secondary mb-0">{{ __('Schools you are assigned to.') }}</p>
        </div>
        <button type="button" class="btn btn-primary" wire:click="$set('showCreateModal', true)">
            <i class="ri ri-add-line me-1"></i>{{ __('New school') }}
        </button>
    </div>

    <x-data-table
        :columns="$this->tableColumns()"
        :rows="$schools"
        :search="$search"
        :sort-column="$sortColumn"
        :sort-direction="$sortDirection"
        :per-page="$perPage"
        :column-filters="$columnFilters"
        :hidden-columns="$hiddenColumns"
        :per-page-options="$this->perPageOptions()"
    >
        @forelse ($schools as $school)
            <tr wire:key="school-{{ $school->id }}">
                @if ($this->columnVisible('code'))
                    <td><span class="fw-medium">{{ $school->code }}</span></td>
                @endif
                @if ($this->columnVisible('name'))
                    <td>{{ $school->name }}</td>
                @endif
                @if ($this->columnVisible('category'))
                    <td class="text-capitalize">{{ $school->category }}</td>
                @endif
                @if ($this->columnVisible('status'))
                    <td>
                        <span class="badge {{ $school->status === 'active' ? 'text-bg-success' : 'text-bg-secondary' }} text-capitalize">
                            {{ $school->status }}
                        </span>
                    </td>
                @endif
                <td class="text-end">
                    <a href="{{ route('schools.profile', $school) }}" class="btn btn-sm btn-outline-secondary" wire:navigate>
                        {{ __('Manage') }}
                    </a>
                </td>
            </tr>
        @empty
            <tr>
                <td colspan="5" class="text-center text-body-secondary py-4">
                    {{ __('No schools yet.') }}
                </td>
            </tr>
        @endforelse
    </x-data-table>

    @if ($showCreateModal)
        <div class="modal show d-block" tabindex="-1" style="background: rgba(0, 0, 0, .5);">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <form wire:submit="create">
                        <div class="modal-header">
                            <h5 class="modal-title">{{ __('New school') }}</h5>
                            <button type="button" class="btn-close" wire:click="$set('showCreateModal', false)" aria-label="{{ __('Close') }}"></button>
                        </div>
                        <div class="modal-body">
                            <div class="form-floating form-floating-outline mb-3">
                                <input type="text" class="form-control @error('code') is-invalid @enderror" id="school-code" wire:model="code" placeholder=" ">
                                <label for="school-code">{{ __('Code (e.g. SGH)') }}</label>
                                @error('code')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="form-floating form-floating-outline mb-3">
                                <input type="text" class="form-control @error('name') is-invalid @enderror" id="school-name" wire:model="name" placeholder=" ">
                                <label for="school-name">{{ __('Name') }}</label>
                                @error('name')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="form-floating form-floating-outline">
                                <select class="form-select @error('category') is-invalid @enderror" id="school-category" wire:model="category">
                                    <option value="government">{{ __('Government') }}</option>
                                    <option value="council">{{ __('Council') }}</option>
                                    <option value="mission">{{ __('Mission') }}</option>
                                    <option value="trust">{{ __('Trust') }}</option>
                                    <option value="private">{{ __('Private') }}</option>
                                </select>
                                <label for="school-category">{{ __('Category') }}</label>
                                @error('category')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-outline-secondary" wire:click="$set('showCreateModal', false)">{{ __('Cancel') }}</button>
                            <button type="submit" class="btn btn-primary" wire:loading.attr="disabled">{{ __('Create') }}</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endif
</div>
