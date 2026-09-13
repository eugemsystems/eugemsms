<div>
    <div class="d-flex align-items-center gap-2 mb-4">
        <div class="flex-grow-1">
            <h4 class="mb-1">{{ __('Billing run history') }}</h4>
            <p class="text-body-secondary mb-0">{{ __('A committed run can never be un-run — corrections are credit notes and supplementary invoices.') }}</p>
        </div>
        <a href="{{ route('finance.billing.run-wizard', $school) }}" class="btn btn-primary" wire:navigate>
            <i class="ri ri-add-line me-1"></i>{{ __('New run') }}
        </a>
    </div>

    <x-data-table
        :columns="$this->tableColumns()"
        :rows="$runs"
        :search="$search"
        :sort-column="$sortColumn"
        :sort-direction="$sortDirection"
        :per-page="$perPage"
        :column-filters="$columnFilters"
        :hidden-columns="$hiddenColumns"
        :per-page-options="$this->perPageOptions()"
        :with-actions="false"
    >
        @forelse ($runs as $run)
            <tr wire:key="run-{{ $run->id }}" role="button" onclick="window.location='{{ route('finance.billing.preview', ['school' => $school, 'billingRun' => $run]) }}'">
                @if ($this->columnVisible('id'))
                    <td>
                        #{{ $run->id }}
                        <div class="text-body-secondary small">{{ $run->term?->name }}</div>
                    </td>
                @endif
                @if ($this->columnVisible('status'))
                    <td>
                        <span class="badge {{ match ($run->status) { 'committed' => 'text-bg-success', 'approved' => 'text-bg-info', 'preview' => 'text-bg-warning', 'failed' => 'text-bg-danger', default => 'text-bg-secondary' } }}">
                            {{ \Illuminate\Support\Str::headline($run->status) }}
                        </span>
                    </td>
                @endif
                @if ($this->columnVisible('total_learners'))
                    <td>{{ $run->total_learners }}</td>
                @endif
                @if ($this->columnVisible('exception_count'))
                    <td>{{ $run->exception_count }}</td>
                @endif
                @if ($this->columnVisible('total_net_minor'))
                    <td>{{ number_format($run->total_net_minor / 100, 2) }}</td>
                @endif
            </tr>
        @empty
            <tr>
                <td colspan="5" class="text-center text-body-secondary py-4">{{ __('No billing runs yet.') }}</td>
            </tr>
        @endforelse
    </x-data-table>
</div>
