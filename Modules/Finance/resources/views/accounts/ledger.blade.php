<div>
    <div class="d-flex align-items-center gap-2 mb-4">
        <a href="{{ route('finance.accounts.tree', $school) }}" class="btn btn-icon btn-outline-secondary btn-sm" wire:navigate>
            <i class="ri ri-arrow-left-line"></i>
        </a>
        <div>
            <h4 class="mb-1">{{ $account->code }} — {{ $account->name }}</h4>
            <p class="text-body-secondary mb-0">{{ __('Opening balance :amount :currency', ['amount' => number_format($opening->minor / 100, 2), 'currency' => $currency]) }}</p>
        </div>
    </div>

    <x-data-table
        :columns="$this->tableColumns()"
        :rows="$lines"
        :search="$search"
        :sort-column="$sortColumn"
        :sort-direction="$sortDirection"
        :per-page="$perPage"
        :column-filters="$columnFilters"
        :hidden-columns="$hiddenColumns"
        :per-page-options="$this->perPageOptions()"
        :with-actions="false"
    >
        @forelse ($lines as $line)
            <tr wire:key="line-{{ $line->id }}">
                @if ($this->columnVisible('effective_at'))
                    <td>{{ $line->effective_at->format('d M Y') }}</td>
                @endif
                @if ($this->columnVisible('narration'))
                    <td>
                        {{ $line->narration ?? $line->journal->narration }}
                        <div class="text-body-secondary small">{{ $line->journal->journal_number }}</div>
                    </td>
                @endif
                @if ($this->columnVisible('direction'))
                    <td>
                        <span class="badge {{ $line->direction === 'DR' ? 'text-bg-primary' : 'text-bg-warning' }}">{{ $line->direction }}</span>
                    </td>
                @endif
                @if ($this->columnVisible('amount_minor'))
                    <td>{{ number_format($line->amount_minor / 100, 2) }} {{ $line->currency }}</td>
                @endif
                @if ($this->columnVisible('running_balance'))
                    <td>{{ number_format(($runningBalances[$line->id] ?? 0) / 100, 2) }}</td>
                @endif
            </tr>
        @empty
            <tr>
                <td colspan="5" class="text-center text-body-secondary py-4">{{ __('No lines posted to this account yet.') }}</td>
            </tr>
        @endforelse
    </x-data-table>
</div>
