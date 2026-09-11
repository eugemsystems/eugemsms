<div>
    <div class="d-flex align-items-center justify-content-between mb-4">
        <div>
            <h4 class="mb-1">{{ __('Setting change history') }}</h4>
            <p class="text-body-secondary mb-0">{{ __('For :school.', ['school' => $school->name]) }}</p>
        </div>
        <a href="{{ route('settings.index', $school) }}" class="btn btn-outline-secondary" wire:navigate>
            <i class="ri ri-arrow-left-line me-1"></i>{{ __('Back to settings') }}
        </a>
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
            <tr wire:key="log-{{ $entry->id }}">
                @if ($this->columnVisible('setting_key'))
                    <td><code>{{ $entry->setting_key }}</code></td>
                @endif
                @if ($this->columnVisible('old_value'))
                    <td class="text-body-secondary">{{ $entry->old_value ?? '—' }}</td>
                @endif
                @if ($this->columnVisible('new_value'))
                    <td>{{ $entry->new_value ?? '—' }}</td>
                @endif
                @if ($this->columnVisible('changed_at'))
                    <td>{{ $entry->changed_at->format('d M Y H:i') }}</td>
                @endif
                @if ($this->columnVisible('changed_by'))
                    <td>{{ $entry->changedBy?->name ?? '—' }}</td>
                @endif
            </tr>
        @empty
            <tr>
                <td colspan="5" class="text-center text-body-secondary py-4">
                    {{ __('No setting changes recorded yet.') }}
                </td>
            </tr>
        @endforelse
    </x-data-table>
</div>
