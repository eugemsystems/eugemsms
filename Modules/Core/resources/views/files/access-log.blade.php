<div>
    <div class="d-flex align-items-center gap-2 mb-4">
        <a href="{{ route('files.index', $school) }}" class="btn btn-icon btn-outline-secondary btn-sm" wire:navigate>
            <i class="ri ri-arrow-left-line"></i>
        </a>
        <div>
            <h4 class="mb-1">{{ __('File access log') }}</h4>
            <p class="text-body-secondary mb-0">{{ __('Every recorded view or download of a sensitive-category file.') }}</p>
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
        :with-actions="false"
    >
        @forelse ($entries as $entry)
            <tr wire:key="file-access-{{ $entry->id }}">
                @if ($this->columnVisible('file'))
                    <td>{{ $entry->file?->original_name ?? '—' }}</td>
                @endif
                @if ($this->columnVisible('user'))
                    <td>{{ $entry->user?->name ?? '—' }}</td>
                @endif
                @if ($this->columnVisible('action'))
                    <td>{{ \Illuminate\Support\Str::headline($entry->action) }}</td>
                @endif
                @if ($this->columnVisible('ip_address'))
                    <td>{{ $entry->ip_address ?? '—' }}</td>
                @endif
                @if ($this->columnVisible('accessed_at'))
                    <td>{{ $entry->accessed_at->format('d M Y H:i') }}</td>
                @endif
            </tr>
        @empty
            <tr>
                <td colspan="5" class="text-center text-body-secondary py-4">{{ __('No sensitive file access has been recorded yet.') }}</td>
            </tr>
        @endforelse
    </x-data-table>
</div>
