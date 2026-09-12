<div>
    <div class="d-flex align-items-center gap-2 mb-4 flex-wrap">
        <a href="{{ route('schools.index') }}" class="btn btn-icon btn-outline-secondary btn-sm" wire:navigate>
            <i class="ri ri-arrow-left-line"></i>
        </a>
        <div class="flex-grow-1">
            <h4 class="mb-1">{{ __('Audit explorer') }}</h4>
            <p class="text-body-secondary mb-0">{{ __('Every recorded change for :school.', ['school' => $school->name]) }}</p>
        </div>
        <a href="{{ route('audit.financial', $school) }}" class="btn btn-outline-secondary" wire:navigate>{{ __('Financial stream') }}</a>
        <a href="{{ route('audit.security', $school) }}" class="btn btn-outline-secondary" wire:navigate>{{ __('Security events') }}</a>
        <a href="{{ route('audit.access', $school) }}" class="btn btn-outline-secondary" wire:navigate>{{ __('Access log') }}</a>
        <a href="{{ route('audit.integrity', $school) }}" class="btn btn-outline-secondary" wire:navigate>{{ __('Integrity') }}</a>
        <a href="{{ route('audit.export', $school) }}" class="btn btn-outline-secondary" wire:navigate>{{ __('Export') }}</a>
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
            <tr wire:key="entry-{{ $entry->id }}">
                @if ($this->columnVisible('log_name'))
                    <td>{{ $entry->log_name }}</td>
                @endif
                @if ($this->columnVisible('description'))
                    <td>
                        {{ $entry->description }}
                        <div class="small text-body-secondary">{{ $entry->causer?->name ?? __('System') }}</div>
                    </td>
                @endif
                @if ($this->columnVisible('event'))
                    <td>
                        @if ($entry->event)
                            <span class="badge text-bg-{{ match ($entry->event) { 'created' => 'success', 'deleted' => 'danger', 'restored' => 'info', default => 'secondary' } }}">
                                {{ \Illuminate\Support\Str::headline($entry->event) }}
                            </span>
                        @endif
                    </td>
                @endif
                @if ($this->columnVisible('subject_type'))
                    <td>{{ $entry->subject_type ? class_basename($entry->subject_type).' #'.$entry->subject_id : '—' }}</td>
                @endif
                @if ($this->columnVisible('created_at'))
                    <td>{{ $entry->created_at->format('d M Y H:i') }}</td>
                @endif
            </tr>
        @empty
            <tr>
                <td colspan="5" class="text-center text-body-secondary py-4">{{ __('No activity recorded yet.') }}</td>
            </tr>
        @endforelse
    </x-data-table>
</div>
