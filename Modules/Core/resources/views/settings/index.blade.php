<div>
    @include('core::schools.partials.tabs', ['school' => $school, 'active' => 'settings'])

    <div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-2">
        <p class="text-body-secondary mb-0">{{ __('Every registered setting, with the value currently in effect for :school.', ['school' => $school->name]) }}</p>
        <div class="d-flex flex-wrap gap-2">
            <a href="{{ route('settings.history', $school) }}" class="btn btn-outline-secondary" wire:navigate>
                <i class="ri ri-history-line me-1"></i>{{ __('Change history') }}
            </a>
            <a href="{{ route('custom-fields.index', $school) }}" class="btn btn-outline-secondary" wire:navigate>
                <i class="ri ri-input-method-line me-1"></i>{{ __('Custom fields') }}
            </a>
            <a href="{{ route('settings.profiles', $school) }}" class="btn btn-outline-secondary" wire:navigate>
                <i class="ri ri-archive-line me-1"></i>{{ __('Profiles') }}
            </a>
        </div>
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
            <tr wire:key="setting-{{ $definition->id }}">
                @if ($this->columnVisible('key'))
                    <td><code>{{ $definition->key }}</code></td>
                @endif
                @if ($this->columnVisible('label'))
                    <td>{{ $definition->label }}</td>
                @endif
                @if ($this->columnVisible('module_code'))
                    <td>{{ $definition->module_code }}</td>
                @endif
                @if ($this->columnVisible('data_type'))
                    <td><span class="badge text-bg-light">{{ $definition->data_type }}</span></td>
                @endif
                @if ($this->columnVisible('value'))
                    <td class="text-body-secondary">
                        @if ($definition->is_encrypted)
                            <span class="fst-italic">{{ __('(encrypted)') }}</span>
                        @else
                            @php $value = $resolved[$definition->key] ?? null; @endphp
                            @if (is_bool($value))
                                {{ $value ? __('Yes') : __('No') }}
                            @elseif (is_array($value))
                                <span class="fst-italic">{{ __('(structured value)') }}</span>
                            @else
                                {{ $value === null || $value === '' ? '—' : \Illuminate\Support\Str::limit((string) $value, 60) }}
                            @endif
                        @endif
                    </td>
                @endif
                <td class="text-end">
                    <div class="d-flex justify-content-end gap-1">
                        <a href="{{ route('settings.edit', [$school, $definition->key]) }}" class="btn btn-icon btn-sm btn-outline-primary" wire:navigate title="{{ __('Edit') }}" aria-label="{{ __('Edit') }}">
                            <i class="icon-base ri ri-edit-line icon-22px"></i>
                        </a>
                    </div>
                </td>
            </tr>
        @empty
            <tr>
                <td colspan="6" class="text-center text-body-secondary py-4">
                    {{ __('No settings are registered yet.') }}
                </td>
            </tr>
        @endforelse
    </x-data-table>
</div>
