<div>
    <div class="mb-4">
        <h4 class="mb-1">{{ __('Conversion log') }}</h4>
        <p class="text-body-secondary mb-0">{{ __('Every conversion performed — the rate, source, and both amounts, so any figure is re-derivable.') }}</p>
    </div>

    <x-data-table
        :columns="$this->tableColumns()"
        :rows="$conversions"
        :search="$search"
        :sort-column="$sortColumn"
        :sort-direction="$sortDirection"
        :per-page="$perPage"
        :column-filters="$columnFilters"
        :hidden-columns="$hiddenColumns"
        :per-page-options="$this->perPageOptions()"
        :with-actions="false"
    >
        @forelse ($conversions as $conversion)
            <tr wire:key="conversion-{{ $conversion->id }}">
                @if ($this->columnVisible('context_type'))
                    <td>{{ \Illuminate\Support\Str::headline($conversion->context_type) }}</td>
                @endif
                @if ($this->columnVisible('from_currency'))
                    <td>{{ $conversion->from_currency }} {{ number_format($conversion->from_amount_minor / 100, 2) }}</td>
                @endif
                @if ($this->columnVisible('to_currency'))
                    <td>{{ $conversion->to_currency }} {{ number_format($conversion->to_amount_minor / 100, 2) }}</td>
                @endif
                @if ($this->columnVisible('rate_used'))
                    <td>{{ $conversion->rate_used }}</td>
                @endif
                @if ($this->columnVisible('converted_at'))
                    <td>{{ $conversion->converted_at->format('d M Y H:i') }}</td>
                @endif
            </tr>
        @empty
            <tr>
                <td colspan="5" class="text-center text-body-secondary py-4">{{ __('No conversions recorded yet.') }}</td>
            </tr>
        @endforelse
    </x-data-table>
</div>
