<div>
    <div class="d-flex align-items-center gap-2 mb-4">
        <a href="{{ route('scheduling.tasks') }}" class="btn btn-icon btn-outline-secondary btn-sm" wire:navigate>
            <i class="ri ri-arrow-left-line"></i>
        </a>
        <div>
            <h4 class="mb-1">{{ __(':name — run history', ['name' => $task->name]) }}</h4>
            <p class="text-body-secondary mb-0">{{ $task->description }}</p>
        </div>
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
            <tr wire:key="run-{{ $run->id }}">
                @if ($this->columnVisible('status'))
                    @php
                        $runBadge = match ($run->status) {
                            'completed' => 'success',
                            'failed', 'timed_out' => 'danger',
                            default => 'warning',
                        };
                    @endphp
                    <td><span class="badge text-bg-{{ $runBadge }}">{{ \Illuminate\Support\Str::headline($run->status) }}</span></td>
                @endif
                @if ($this->columnVisible('started_at'))
                    <td>{{ $run->started_at->format('d M Y H:i:s') }}</td>
                @endif
                @if ($this->columnVisible('duration_ms'))
                    <td>{{ $run->duration_ms !== null ? number_format($run->duration_ms) : '—' }}</td>
                @endif
                @if ($this->columnVisible('error'))
                    <td class="small text-danger">{{ $run->error }}</td>
                @endif
            </tr>
        @empty
            <tr>
                <td colspan="4" class="text-center text-body-secondary py-4">{{ __('This task has never run.') }}</td>
            </tr>
        @endforelse
    </x-data-table>
</div>
