<div>
    <div class="d-flex align-items-center gap-2 mb-4">
        <div class="flex-grow-1">
            <h4 class="mb-1">{{ __('Journals') }}</h4>
            <p class="text-body-secondary mb-0">{{ __(':school\'s posted and draft journals.', ['school' => $school->name]) }}</p>
        </div>
        <a href="{{ route('finance.journals.create', $school) }}" class="btn btn-primary" wire:navigate>
            <i class="ri ri-add-line me-1"></i>{{ __('New manual journal') }}
        </a>
    </div>

    <x-data-table
        :columns="$this->tableColumns()"
        :rows="$journals"
        :search="$search"
        :sort-column="$sortColumn"
        :sort-direction="$sortDirection"
        :per-page="$perPage"
        :column-filters="$columnFilters"
        :hidden-columns="$hiddenColumns"
        :per-page-options="$this->perPageOptions()"
        :with-actions="false"
    >
        @forelse ($journals as $journal)
            <tr wire:key="journal-{{ $journal->id }}" role="button" onclick="window.location='{{ route('finance.journals.show', ['school' => $school, 'journal' => $journal]) }}'">
                @if ($this->columnVisible('journal_number'))
                    <td>{{ $journal->journal_number }}</td>
                @endif
                @if ($this->columnVisible('journal_type'))
                    <td>{{ $journal->journal_type }}</td>
                @endif
                @if ($this->columnVisible('narration'))
                    <td>{{ \Illuminate\Support\Str::limit($journal->narration, 60) }}</td>
                @endif
                @if ($this->columnVisible('effective_at'))
                    <td>{{ $journal->effective_at->format('d M Y') }}</td>
                @endif
                @if ($this->columnVisible('status'))
                    <td>
                        <span class="badge {{ $journal->status === 'posted' ? 'text-bg-success' : 'text-bg-warning' }}">
                            {{ \Illuminate\Support\Str::headline($journal->status) }}
                        </span>
                        @if ($journal->isReversed())
                            <span class="badge text-bg-secondary">{{ __('Reversed') }}</span>
                        @endif
                    </td>
                @endif
            </tr>
        @empty
            <tr>
                <td colspan="5" class="text-center text-body-secondary py-4">{{ __('No journals yet.') }}</td>
            </tr>
        @endforelse
    </x-data-table>
</div>
