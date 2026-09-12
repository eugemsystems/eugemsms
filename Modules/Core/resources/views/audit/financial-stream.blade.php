<div>
    <div class="d-flex align-items-center gap-2 mb-4 flex-wrap">
        <a href="{{ route('audit.explorer', $school) }}" class="btn btn-icon btn-outline-secondary btn-sm" wire:navigate>
            <i class="ri ri-arrow-left-line"></i>
        </a>
        <div class="flex-grow-1">
            <h4 class="mb-1">{{ __('Financial audit stream') }}</h4>
            <p class="text-body-secondary mb-0">{{ __('Append-only and hash-chained — every financial event, in sequence.') }}</p>
        </div>
        <button type="button" class="btn btn-outline-primary" wire:click="verifyChain" wire:loading.attr="disabled">
            <i class="ri ri-shield-check-line me-1"></i>{{ __('Verify chain') }}
        </button>
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
            <tr wire:key="fin-entry-{{ $entry->id }}">
                @if ($this->columnVisible('sequence'))
                    <td>{{ $entry->sequence }}</td>
                @endif
                @if ($this->columnVisible('event_type'))
                    <td>{{ \Illuminate\Support\Str::headline($entry->event_type) }}</td>
                @endif
                @if ($this->columnVisible('amount_minor'))
                    <td>
                        @if ($entry->amount_minor !== null)
                            {{ number_format($entry->amount_minor / 100, 2) }} {{ $entry->amount_currency }}
                        @else
                            —
                        @endif
                    </td>
                @endif
                @if ($this->columnVisible('causer'))
                    <td>{{ $entry->causer?->name }}</td>
                @endif
                @if ($this->columnVisible('occurred_at'))
                    <td>{{ $entry->occurred_at->format('d M Y H:i:s') }}</td>
                @endif
            </tr>
        @empty
            <tr>
                <td colspan="5" class="text-center text-body-secondary py-4">{{ __('No financial events recorded yet.') }}</td>
            </tr>
        @endforelse
    </x-data-table>
</div>
