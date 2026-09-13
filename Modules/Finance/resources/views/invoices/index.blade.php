<div>
    <div class="mb-4">
        <h4 class="mb-1">{{ __('Invoices') }}</h4>
        <p class="text-body-secondary mb-0">{{ __('Every invoice ever issued — an issued invoice is never edited, only voided and replaced.') }}</p>
    </div>

    <x-data-table
        :columns="$this->tableColumns()"
        :rows="$invoices"
        :search="$search"
        :sort-column="$sortColumn"
        :sort-direction="$sortDirection"
        :per-page="$perPage"
        :column-filters="$columnFilters"
        :hidden-columns="$hiddenColumns"
        :per-page-options="$this->perPageOptions()"
        :with-actions="false"
    >
        @forelse ($invoices as $invoice)
            <tr wire:key="invoice-{{ $invoice->id }}" role="button" onclick="window.location='{{ route('finance.invoices.show', ['school' => $school, 'invoice' => $invoice]) }}'">
                @if ($this->columnVisible('invoice_number'))
                    <td>
                        {{ $invoice->invoice_number }}
                        <div class="text-body-secondary small">{{ $invoice->student->admission_number }} — {{ $invoice->student->fullName() }}</div>
                    </td>
                @endif
                @if ($this->columnVisible('invoice_type'))
                    <td>{{ \Illuminate\Support\Str::headline($invoice->invoice_type) }}</td>
                @endif
                @if ($this->columnVisible('due_date'))
                    <td>{{ $invoice->due_date->format('d M Y') }}</td>
                @endif
                @if ($this->columnVisible('balance_minor'))
                    <td>{{ number_format($invoice->balance_minor / 100, 2) }} {{ $invoice->currency }}</td>
                @endif
                @if ($this->columnVisible('status'))
                    <td>
                        <span class="badge {{ match ($invoice->status) { 'paid' => 'text-bg-success', 'voided' => 'text-bg-secondary', 'written_off' => 'text-bg-dark', 'overdue' => 'text-bg-danger', default => 'text-bg-warning' } }}">
                            {{ \Illuminate\Support\Str::headline($invoice->status) }}
                        </span>
                    </td>
                @endif
            </tr>
        @empty
            <tr>
                <td colspan="5" class="text-center text-body-secondary py-4">{{ __('No invoices yet.') }}</td>
            </tr>
        @endforelse
    </x-data-table>
</div>
