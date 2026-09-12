<div>
    <div class="d-flex align-items-center gap-2 mb-4">
        <a href="{{ route('audit.explorer', $school) }}" class="btn btn-icon btn-outline-secondary btn-sm" wire:navigate>
            <i class="ri ri-arrow-left-line"></i>
        </a>
        <div>
            <h4 class="mb-1">{{ __('Data access log') }}</h4>
            <p class="text-body-secondary mb-0">{{ __('Every recorded read of sensitive data.') }}</p>
        </div>
    </div>

    <x-data-table
        :columns="$this->tableColumns()"
        :rows="$entries"
        :search="$search"
        :sort-column="$sortColumn"
        :sort-direction="$sortDirection"
        :per-page="$perPage"
        :column-filters="$columnFilters"
        :hidden-columns="$hiddenColumns"
        :per-page-options="$this->perPageOptions()"
    >
        @forelse ($entries as $entry)
            <tr wire:key="access-{{ $entry->id }}">
                @if ($this->columnVisible('resource_type'))
                    <td>{{ \Illuminate\Support\Str::headline($entry->resource_type) }}</td>
                @endif
                @if ($this->columnVisible('access_type'))
                    <td>{{ \Illuminate\Support\Str::headline($entry->access_type) }}</td>
                @endif
                @if ($this->columnVisible('user'))
                    <td>{{ $entry->user?->name }}</td>
                @endif
                @if ($this->columnVisible('record_count'))
                    <td>{{ $entry->record_count ?? '—' }}</td>
                @endif
                @if ($this->columnVisible('purpose'))
                    <td>{{ $entry->purpose }}</td>
                @endif
                @if ($this->columnVisible('accessed_at'))
                    <td>{{ $entry->accessed_at->format('d M Y H:i') }}</td>
                @endif
            </tr>
        @empty
            <tr>
                <td colspan="6" class="text-center text-body-secondary py-4">{{ __('No access has been recorded yet.') }}</td>
            </tr>
        @endforelse
    </x-data-table>
</div>
