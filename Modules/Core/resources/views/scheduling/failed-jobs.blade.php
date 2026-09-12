<div>
    <div class="d-flex align-items-center gap-2 mb-4 flex-wrap">
        <div class="flex-grow-1">
            <h4 class="mb-1">{{ __('Failed jobs') }}</h4>
            <p class="text-body-secondary mb-0">{{ __('Retained for 30 days, retryable individually or in bulk.') }}</p>
        </div>
        <button type="button" class="btn btn-primary" wire:click="retrySelected" wire:loading.attr="disabled">
            <i class="ri ri-restart-line me-1"></i>{{ __('Retry selected') }}
        </button>
    </div>

    <x-data-table
        :columns="$this->tableColumns()"
        :rows="$jobs"
        :search="$search"
        :sort-column="$sortColumn"
        :sort-direction="$sortDirection"
        :per-page="$perPage"
        :column-filters="$columnFilters"
        :hidden-columns="$hiddenColumns"
        :per-page-options="$this->perPageOptions()"
        :with-selection="true"
    >
        @forelse ($jobs as $job)
            <tr wire:key="failed-job-{{ $job->id }}">
                <td>
                    <input type="checkbox" class="form-check-input" wire:model="selected" value="{{ $job->uuid }}">
                </td>
                @if ($this->columnVisible('connection'))
                    <td>
                        {{ $job->connection }}
                        <div class="small text-body-secondary">{{ $job->displayName() }}</div>
                    </td>
                @endif
                @if ($this->columnVisible('queue'))
                    <td>{{ $job->queue }}</td>
                @endif
                @if ($this->columnVisible('exception'))
                    <td class="small text-danger">{{ \Illuminate\Support\Str::limit($job->exceptionSummary(), 120) }}</td>
                @endif
                @if ($this->columnVisible('failed_at'))
                    <td>{{ $job->failed_at->format('d M Y H:i') }}</td>
                @endif
                <td class="text-end">
                    <button type="button" class="btn btn-icon btn-sm btn-outline-primary" wire:click="retry('{{ $job->uuid }}')" title="{{ __('Retry') }}" aria-label="{{ __('Retry') }}">
                        <i class="icon-base ri ri-restart-line icon-22px"></i>
                    </button>
                    <button type="button" class="btn btn-icon btn-sm btn-outline-danger" wire:click="forget('{{ $job->uuid }}')" wire:confirm="{{ __('Permanently remove this failed job?') }}" title="{{ __('Remove') }}" aria-label="{{ __('Remove') }}">
                        <i class="icon-base ri ri-delete-bin-line icon-22px"></i>
                    </button>
                </td>
            </tr>
        @empty
            <tr>
                <td colspan="6" class="text-center text-body-secondary py-4">{{ __('No failed jobs — queues are healthy.') }}</td>
            </tr>
        @endforelse
    </x-data-table>
</div>
