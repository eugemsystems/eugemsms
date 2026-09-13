<div>
    <div class="d-flex align-items-center gap-2 mb-4">
        <div class="flex-grow-1">
            <h4 class="mb-1">{{ __('Fee structures') }}</h4>
            <p class="text-body-secondary mb-0">{{ __('Every structure this school has defined, every version.') }}</p>
        </div>
        <a href="{{ route('finance.fees.structure-builder.create', $school) }}" class="btn btn-primary" wire:navigate>
            <i class="ri ri-add-line me-1"></i>{{ __('New structure') }}
        </a>
    </div>

    <x-data-table
        :columns="$this->tableColumns()"
        :rows="$structures"
        :search="$search"
        :sort-column="$sortColumn"
        :sort-direction="$sortDirection"
        :per-page="$perPage"
        :column-filters="$columnFilters"
        :hidden-columns="$hiddenColumns"
        :per-page-options="$this->perPageOptions()"
    >
        @forelse ($structures as $structure)
            <tr wire:key="structure-{{ $structure->id }}">
                @if ($this->columnVisible('name'))
                    <td>
                        {{ $structure->name }}
                        <div class="text-body-secondary small">
                            {{ $structure->academicYear->name }}
                            @if ($structure->term) · {{ $structure->term->name }} @else · {{ __('All terms') }} @endif
                        </div>
                    </td>
                @endif
                @if ($this->columnVisible('version'))
                    <td>v{{ $structure->version }}</td>
                @endif
                @if ($this->columnVisible('priority'))
                    <td>{{ $structure->priority }}</td>
                @endif
                @if ($this->columnVisible('status'))
                    <td>
                        <span class="badge {{ match ($structure->status) { 'active' => 'text-bg-success', 'draft' => 'text-bg-warning', 'superseded' => 'text-bg-secondary', default => 'text-bg-light' } }}">
                            {{ \Illuminate\Support\Str::headline($structure->status) }}
                        </span>
                    </td>
                @endif
                <td class="text-end">
                    <div class="d-flex justify-content-end gap-1">
                        <a href="{{ route('finance.fees.structure-versions', ['school' => $school, 'structure' => $structure]) }}" class="btn btn-sm btn-outline-secondary" wire:navigate>{{ __('Versions') }}</a>
                        <a href="{{ route('finance.fees.structure-builder.revise', ['school' => $school, 'structure' => $structure]) }}" class="btn btn-sm btn-outline-primary" wire:navigate>{{ __('Revise') }}</a>
                    </div>
                </td>
            </tr>
        @empty
            <tr>
                <td colspan="5" class="text-center text-body-secondary py-4">{{ __('No fee structures defined yet.') }}</td>
            </tr>
        @endforelse
    </x-data-table>
</div>
