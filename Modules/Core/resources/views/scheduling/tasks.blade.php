<div>
    <div class="d-flex align-items-center gap-2 mb-4 flex-wrap">
        <div class="flex-grow-1">
            <h4 class="mb-1">{{ __('Scheduled tasks') }}</h4>
            <p class="text-body-secondary mb-0">{{ __('Every task registered across the platform.') }}</p>
        </div>
        <button type="button" class="btn btn-outline-secondary" wire:click="checkFreshness">
            <i class="ri ri-alarm-warning-line me-1"></i>{{ __('Check freshness') }}
        </button>
    </div>

    <x-data-table
        :columns="$this->tableColumns()"
        :rows="$tasks"
        :search="$search"
        :sort-column="$sortColumn"
        :sort-direction="$sortDirection"
        :per-page="$perPage"
        :column-filters="$columnFilters"
        :hidden-columns="$hiddenColumns"
        :per-page-options="$this->perPageOptions()"
    >
        @forelse ($tasks as $task)
            <tr wire:key="task-{{ $task->id }}">
                @if ($this->columnVisible('name'))
                    <td>
                        <a href="{{ route('scheduling.tasks.runs', $task->key) }}" wire:navigate>{{ $task->name }}</a>
                        <div class="small text-body-secondary font-monospace">{{ $task->key }}</div>
                    </td>
                @endif
                @if ($this->columnVisible('module_code'))
                    <td>{{ $task->module_code }}</td>
                @endif
                @if ($this->columnVisible('schedule_expression'))
                    <td><code>{{ $task->schedule_expression }}</code></td>
                @endif
                @if ($this->columnVisible('is_enabled'))
                    <td>
                        @if ($task->is_enabled)
                            <span class="badge text-bg-success">{{ __('Enabled') }}</span>
                        @else
                            <span class="badge text-bg-secondary">{{ __('Disabled') }}</span>
                        @endif
                    </td>
                @endif
                @if ($this->columnVisible('is_per_school'))
                    <td>{{ $task->is_per_school ? __('Yes') : __('No') }}</td>
                @endif
                @if ($this->columnVisible('latest_run'))
                    <td>
                        @if ($task->latestRun)
                            @php
                                $runBadge = match ($task->latestRun->status) {
                                    'completed' => 'success',
                                    'failed', 'timed_out' => 'danger',
                                    default => 'warning',
                                };
                            @endphp
                            <span class="badge text-bg-{{ $runBadge }}">{{ \Illuminate\Support\Str::headline($task->latestRun->status) }}</span>
                            <span class="small text-body-secondary">{{ $task->latestRun->started_at->diffForHumans() }}</span>
                        @else
                            <span class="text-body-secondary small">{{ __('Never run') }}</span>
                        @endif
                    </td>
                @endif
            </tr>
        @empty
            <tr>
                <td colspan="6" class="text-center text-body-secondary py-4">{{ __('No scheduled tasks are registered yet.') }}</td>
            </tr>
        @endforelse
    </x-data-table>
</div>
