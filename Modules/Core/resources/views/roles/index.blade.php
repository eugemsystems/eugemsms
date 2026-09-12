<div>
    <div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-2">
        <div>
            <h4 class="mb-1">{{ __('Roles') }}</h4>
            <p class="text-body-secondary mb-0">{{ __('School-owned roles for :school, plus system templates available to every school.', ['school' => $school->name]) }}</p>
        </div>
        <a href="{{ route('permissions.explorer', $school) }}" class="btn btn-outline-secondary" wire:navigate>
            <i class="ri ri-search-eye-line me-1"></i>{{ __('Permission explorer') }}
        </a>
    </div>

    <x-data-table
        :columns="$this->tableColumns()"
        :rows="$roles"
        :search="$search"
        :sort-column="$sortColumn"
        :sort-direction="$sortDirection"
        :per-page="$perPage"
        :column-filters="$columnFilters"
        :hidden-columns="$hiddenColumns"
        :per-page-options="$this->perPageOptions()"
    >
        @forelse ($roles as $role)
            <tr wire:key="role-{{ $role->id }}">
                @if ($this->columnVisible('display_name'))
                    <td>
                        <div class="fw-medium">{{ $role->display_name }}</div>
                        <div class="small text-body-secondary">{{ $role->name }}</div>
                    </td>
                @endif
                @if ($this->columnVisible('category'))
                    <td>{{ \Illuminate\Support\Str::headline($role->category) }}</td>
                @endif
                @if ($this->columnVisible('is_system'))
                    <td>
                        @if ($role->is_system)
                            <span class="badge text-bg-info">{{ __('System template') }}</span>
                        @else
                            <span class="badge text-bg-secondary">{{ __('School role') }}</span>
                        @endif
                        @if ($role->is_vendor_only)
                            <span class="badge text-bg-danger ms-1">{{ __('Vendor only') }}</span>
                        @endif
                    </td>
                @endif
                <td class="text-end">
                    @if ($role->is_system)
                        <button type="button" class="btn btn-icon btn-sm btn-outline-primary" wire:click="openCloneModal({{ $role->id }})" title="{{ __('Clone into a school-owned role') }}" aria-label="{{ __('Clone role') }}">
                            <i class="icon-base ri ri-file-copy-line icon-22px"></i>
                        </button>
                    @else
                        <a href="{{ route('roles.edit', [$school, $role]) }}" class="btn btn-icon btn-sm btn-outline-primary" wire:navigate title="{{ __('Edit permissions') }}" aria-label="{{ __('Edit role') }}">
                            <i class="icon-base ri ri-edit-line icon-22px"></i>
                        </a>
                    @endif
                </td>
            </tr>
        @empty
            <tr>
                <td colspan="4" class="text-center text-body-secondary py-4">{{ __('No roles found.') }}</td>
            </tr>
        @endforelse
    </x-data-table>

    @if ($showCloneModal)
        <div class="modal show d-block" tabindex="-1" style="background: rgba(0, 0, 0, .5);">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <form wire:submit="cloneRole">
                        <div class="modal-header">
                            <h5 class="modal-title">{{ __('Clone :role', ['role' => $cloningRoleName]) }}</h5>
                            <button type="button" class="btn-close" wire:click="$set('showCloneModal', false)" aria-label="{{ __('Close') }}"></button>
                        </div>
                        <div class="modal-body">
                            <p class="text-body-secondary">{{ __('Creates a school-owned, fully editable copy of this system template — the original template is never changed.') }}</p>
                            <div class="form-floating form-floating-outline mb-3">
                                <input type="text" class="form-control @error('newName') is-invalid @enderror" id="clone-name" wire:model="newName" placeholder=" ">
                                <label for="clone-name">{{ __('Internal name (slug)') }}</label>
                                @error('newName') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                            <div class="form-floating form-floating-outline">
                                <input type="text" class="form-control @error('newDisplayName') is-invalid @enderror" id="clone-display-name" wire:model="newDisplayName" placeholder=" ">
                                <label for="clone-display-name">{{ __('Display name') }}</label>
                                @error('newDisplayName') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-outline-secondary" wire:click="$set('showCloneModal', false)">{{ __('Cancel') }}</button>
                            <button type="submit" class="btn btn-primary" wire:loading.attr="disabled">{{ __('Clone role') }}</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endif
</div>
