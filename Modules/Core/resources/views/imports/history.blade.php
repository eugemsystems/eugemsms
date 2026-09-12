<div>
    <div class="d-flex align-items-center gap-2 mb-4">
        <a href="{{ route('imports.index', $school) }}" class="btn btn-icon btn-outline-secondary btn-sm" wire:navigate>
            <i class="ri ri-arrow-left-line"></i>
        </a>
        <div>
            <h4 class="mb-1">{{ __('Import history') }}</h4>
            <p class="text-body-secondary mb-0">{{ __('Every batch run for :school.', ['school' => $school->name]) }}</p>
        </div>
    </div>

    <x-data-table
        :columns="$this->tableColumns()"
        :rows="$batches"
        :search="$search"
        :sort-column="$sortColumn"
        :sort-direction="$sortDirection"
        :per-page="$perPage"
        :column-filters="$columnFilters"
        :hidden-columns="$hiddenColumns"
        :per-page-options="$this->perPageOptions()"
    >
        @forelse ($batches as $batch)
            <tr wire:key="batch-{{ $batch->id }}">
                @if ($this->columnVisible('definition_key'))
                    <td>{{ \Illuminate\Support\Str::headline($batch->definition_key) }}</td>
                @endif
                @if ($this->columnVisible('status'))
                    @php
                        $statusBadge = match ($batch->status) {
                            'completed' => 'success',
                            'failed' => 'danger',
                            'rolled_back' => 'secondary',
                            'validated' => 'info',
                            default => 'warning',
                        };
                    @endphp
                    <td><span class="badge text-bg-{{ $statusBadge }}">{{ \Illuminate\Support\Str::headline($batch->status) }}</span></td>
                @endif
                @if ($this->columnVisible('imported_rows'))
                    <td>{{ $batch->imported_rows }}</td>
                @endif
                @if ($this->columnVisible('failed_rows'))
                    <td>{{ $batch->failed_rows }}</td>
                @endif
                @if ($this->columnVisible('imported_by'))
                    <td>{{ $batch->importedBy?->name ?? '—' }}</td>
                @endif
                @if ($this->columnVisible('started_at'))
                    <td>{{ $batch->started_at?->format('d M Y H:i') ?? '—' }}</td>
                @endif
                <td class="text-end">
                    <a href="{{ route('imports.batches.show', [$school, $batch]) }}" class="btn btn-icon btn-sm btn-outline-primary" wire:navigate title="{{ __('View') }}" aria-label="{{ __('View') }}">
                        <i class="icon-base ri ri-eye-line icon-22px"></i>
                    </a>
                </td>
            </tr>
        @empty
            <tr>
                <td colspan="7" class="text-center text-body-secondary py-4">{{ __('No imports have been run yet.') }}</td>
            </tr>
        @endforelse
    </x-data-table>
</div>
