<div>
    <div class="d-flex align-items-center gap-2 mb-4 flex-wrap">
        <a href="{{ route('schools.index') }}" class="btn btn-icon btn-outline-secondary btn-sm" wire:navigate>
            <i class="ri ri-arrow-left-line"></i>
        </a>
        <div class="flex-grow-1">
            <h4 class="mb-1">{{ __('Numbering series') }}</h4>
            <p class="text-body-secondary mb-0">{{ __('Gapless sequential numbers for :school\'s documents.', ['school' => $school->name]) }}</p>
        </div>
        <a href="{{ route('numbering.gap-report', $school) }}" class="btn btn-outline-secondary" wire:navigate>
            <i class="ri ri-error-warning-line me-1"></i>{{ __('Gap report') }}
        </a>
        <a href="{{ route('numbering.create', $school) }}" class="btn btn-primary" wire:navigate>
            <i class="ri ri-add-line me-1"></i>{{ __('New series') }}
        </a>
    </div>

    <x-data-table
        :columns="$this->tableColumns()"
        :rows="$series"
        :search="$search"
        :sort-column="$sortColumn"
        :sort-direction="$sortDirection"
        :per-page="$perPage"
        :column-filters="$columnFilters"
        :hidden-columns="$hiddenColumns"
        :per-page-options="$this->perPageOptions()"
    >
        @forelse ($series as $item)
            <tr wire:key="series-{{ $item->id }}">
                @if ($this->columnVisible('document_type'))
                    <td>
                        <div class="fw-medium">{{ $item->document_type }}</div>
                        @if ($item->academicYear || $item->term)
                            <div class="small text-body-secondary">
                                {{ $item->academicYear?->name }}{{ $item->term ? ' · '.$item->term->name : '' }}
                            </div>
                        @endif
                    </td>
                @endif
                @if ($this->columnVisible('pattern'))
                    <td><code>{{ $item->prefix }}{{ $item->pattern }}</code></td>
                @endif
                @if ($this->columnVisible('next_sequence'))
                    <td>{{ str_pad((string) $item->next_sequence, $item->sequence_padding, '0', STR_PAD_LEFT) }}</td>
                @endif
                @if ($this->columnVisible('reset_policy'))
                    <td>{{ \Illuminate\Support\Str::headline($item->reset_policy) }}</td>
                @endif
                @if ($this->columnVisible('is_active'))
                    <td>
                        @if ($item->is_active)
                            <span class="badge text-bg-success">{{ __('Active') }}</span>
                        @else
                            <span class="badge text-bg-secondary">{{ __('Inactive') }}</span>
                        @endif
                    </td>
                @endif
                <td class="text-end">
                    <a href="{{ route('numbering.edit', [$school, $item]) }}" class="btn btn-icon btn-sm btn-outline-primary" wire:navigate title="{{ __('Edit') }}" aria-label="{{ __('Edit') }}">
                        <i class="icon-base ri ri-edit-line icon-22px"></i>
                    </a>
                </td>
            </tr>
        @empty
            <tr>
                <td colspan="6" class="text-center text-body-secondary py-4">{{ __('No numbering series yet.') }}</td>
            </tr>
        @endforelse
    </x-data-table>
</div>
