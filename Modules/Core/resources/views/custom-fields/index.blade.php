<div>
    @include('core::schools.partials.tabs', ['school' => $school, 'active' => 'settings'])

    <div class="d-flex align-items-center justify-content-between mb-4">
        <p class="text-body-secondary mb-0">{{ __('Custom fields for :school.', ['school' => $school->name]) }}</p>
        <a href="{{ route('custom-fields.create', $school) }}" class="btn btn-primary" wire:navigate>
            <i class="ri ri-add-line me-1"></i>{{ __('New field') }}
        </a>
    </div>

    <x-data-table
        :columns="$this->tableColumns()"
        :rows="$definitions"
        :search="$search"
        :sort-column="$sortColumn"
        :sort-direction="$sortDirection"
        :per-page="$perPage"
        :column-filters="$columnFilters"
        :hidden-columns="$hiddenColumns"
        :per-page-options="$this->perPageOptions()"
    >
        @forelse ($definitions as $definition)
            <tr wire:key="field-{{ $definition->id }}">
                @if ($this->columnVisible('entity_type'))
                    <td>{{ $definition->entity_type }}</td>
                @endif
                @if ($this->columnVisible('key'))
                    <td><code>{{ $definition->key }}</code></td>
                @endif
                @if ($this->columnVisible('label'))
                    <td>{{ $definition->label }}</td>
                @endif
                @if ($this->columnVisible('data_type'))
                    <td><span class="badge text-bg-light">{{ $definition->data_type }}</span></td>
                @endif
                @if ($this->columnVisible('is_required'))
                    <td>{{ $definition->is_required ? __('Yes') : __('No') }}</td>
                @endif
                @if ($this->columnVisible('is_active'))
                    <td>
                        <span class="badge {{ $definition->is_active ? 'text-bg-success' : 'text-bg-secondary' }}">
                            {{ $definition->is_active ? __('Active') : __('Inactive') }}
                        </span>
                    </td>
                @endif
                <td class="text-end">
                    <div class="d-flex justify-content-end gap-1">
                        @if ($definition->is_active)
                            <button type="button" class="btn btn-icon btn-sm btn-outline-warning" wire:click="deactivate({{ $definition->id }})" wire:confirm="{{ __('Deactivate this field? Existing values are kept.') }}" title="{{ __('Deactivate') }}" aria-label="{{ __('Deactivate') }}">
                                <i class="icon-base ri ri-forbid-line icon-22px"></i>
                            </button>
                        @else
                            <button type="button" class="btn btn-icon btn-sm btn-outline-danger" wire:click="delete({{ $definition->id }})" wire:confirm="{{ __('Permanently delete this field? Only possible with zero recorded values.') }}" title="{{ __('Delete') }}" aria-label="{{ __('Delete') }}">
                                <i class="icon-base ri ri-delete-bin-line icon-22px"></i>
                            </button>
                        @endif
                    </div>
                </td>
            </tr>
        @empty
            <tr>
                <td colspan="7" class="text-center text-body-secondary py-4">
                    {{ __('No custom fields defined yet.') }}
                </td>
            </tr>
        @endforelse
    </x-data-table>
</div>
