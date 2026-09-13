<div>
    <div class="d-flex align-items-center gap-2 mb-4">
        <div class="flex-grow-1">
            <h4 class="mb-1">{{ __('Exchange rates') }}</h4>
            <p class="text-body-secondary mb-0">{{ __('Every rate ever captured — append-only, a correction is always a new row.') }}</p>
        </div>
        <a href="{{ route('finance.currency.capture-rate', $school) }}" class="btn btn-primary" wire:navigate>
            <i class="ri ri-add-line me-1"></i>{{ __('Capture rate') }}
        </a>
    </div>

    <x-data-table
        :columns="$this->tableColumns()"
        :rows="$rates"
        :search="$search"
        :sort-column="$sortColumn"
        :sort-direction="$sortDirection"
        :per-page="$perPage"
        :column-filters="$columnFilters"
        :hidden-columns="$hiddenColumns"
        :per-page-options="$this->perPageOptions()"
        :with-actions="false"
    >
        @forelse ($rates as $rate)
            <tr wire:key="rate-{{ $rate->id }}">
                @if ($this->columnVisible('from_currency'))
                    <td>{{ $rate->from_currency }}</td>
                @endif
                @if ($this->columnVisible('to_currency'))
                    <td>{{ $rate->to_currency }}</td>
                @endif
                @if ($this->columnVisible('rate'))
                    <td>{{ $rate->rate }}</td>
                @endif
                @if ($this->columnVisible('effective_from'))
                    <td>
                        {{ $rate->effective_from->format('d M Y H:i') }}
                        @if ($rate->effective_to)
                            <div class="text-body-secondary small">{{ __('until') }} {{ $rate->effective_to->format('d M Y H:i') }}</div>
                        @endif
                    </td>
                @endif
                @if ($this->columnVisible('status'))
                    <td>
                        <span class="badge {{ match ($rate->status) { 'active' => 'text-bg-success', 'pending' => 'text-bg-warning', 'rejected' => 'text-bg-danger', default => 'text-bg-secondary' } }}">
                            {{ \Illuminate\Support\Str::headline($rate->status) }}
                        </span>
                        <div class="text-body-secondary small">{{ $rate->source->name }}</div>
                    </td>
                @endif
            </tr>
        @empty
            <tr>
                <td colspan="5" class="text-center text-body-secondary py-4">{{ __('No exchange rates captured yet.') }}</td>
            </tr>
        @endforelse
    </x-data-table>
</div>
