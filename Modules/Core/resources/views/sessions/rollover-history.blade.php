<div>
    <div class="d-flex align-items-center justify-content-between mb-4">
        <div>
            <h4 class="mb-1">{{ __('Rollover history') }}</h4>
            <p class="text-body-secondary mb-0">{{ __('Every roll-over attempt for :school.', ['school' => $school->name]) }}</p>
        </div>
        <div class="d-flex gap-2">
            <a href="{{ route('sessions.rollover', $school) }}" class="btn btn-primary" wire:navigate>
                <i class="ri ri-refresh-line me-1"></i>{{ __('New rollover') }}
            </a>
            <a href="{{ route('sessions.years', $school) }}" class="btn btn-outline-secondary" wire:navigate>
                <i class="ri ri-arrow-left-line me-1"></i>{{ __('Back to years') }}
            </a>
        </div>
    </div>

    <x-data-table
        :columns="$this->tableColumns()"
        :rows="$rollovers"
        :search="$search"
        :sort-column="$sortColumn"
        :sort-direction="$sortDirection"
        :per-page="$perPage"
        :column-filters="$columnFilters"
        :hidden-columns="$hiddenColumns"
        :per-page-options="$this->perPageOptions()"
    >
        @forelse ($rollovers as $rollover)
            <tr wire:key="rollover-{{ $rollover->id }}">
                @if ($this->columnVisible('term'))
                    <td>{{ $rollover->fromTerm->name }} &rarr; {{ $rollover->toTerm->name }}</td>
                @endif
                @if ($this->columnVisible('status'))
                    <td>
                        <span @class([
                            'badge text-capitalize',
                            'text-bg-success' => $rollover->status->value === 'completed',
                            'text-bg-danger' => $rollover->status->value === 'failed',
                            'text-bg-secondary' => $rollover->status->value === 'rolled_back',
                            'text-bg-warning' => in_array($rollover->status->value, ['pending', 'validating', 'running'], true),
                        ])>{{ str_replace('_', ' ', $rollover->status->value) }}</span>
                    </td>
                @endif
                @if ($this->columnVisible('started_at'))
                    <td>{{ $rollover->started_at?->format('d M Y H:i') ?? '—' }}</td>
                @endif
                @if ($this->columnVisible('completed_at'))
                    <td>{{ $rollover->completed_at?->format('d M Y H:i') ?? '—' }}</td>
                @endif
                <td class="text-end">
                    <div class="d-flex justify-content-end gap-1">
                        <button type="button" class="btn btn-icon btn-sm btn-outline-secondary" wire:click="view({{ $rollover->id }})" title="{{ __('View') }}" aria-label="{{ __('View') }}">
                            <i class="icon-base ri ri-eye-line icon-22px"></i>
                        </button>
                    </div>
                </td>
            </tr>
        @empty
            <tr>
                <td colspan="5" class="text-center text-body-secondary py-4">
                    {{ __('No rollovers have been run yet.') }}
                </td>
            </tr>
        @endforelse
    </x-data-table>

    @if ($viewing)
        <div class="modal show d-block" tabindex="-1" style="background: rgba(0, 0, 0, .5);">
            <div class="modal-dialog modal-dialog-centered modal-lg">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">{{ $viewing->fromTerm->name }} &rarr; {{ $viewing->toTerm->name }}</h5>
                        <button type="button" class="btn-close" wire:click="$set('viewingId', null)" aria-label="{{ __('Close') }}"></button>
                    </div>
                    <div class="modal-body" style="max-height: 60vh; overflow-y: auto;">
                        @if ($viewing->validation_report)
                            <h6>{{ __('Validation report') }}</h6>
                            <pre class="bg-light p-2 small">{{ json_encode($viewing->validation_report, JSON_PRETTY_PRINT) }}</pre>
                        @endif
                        @if ($viewing->step_log)
                            <h6>{{ __('Step log') }}</h6>
                            <pre class="bg-light p-2 small">{{ json_encode($viewing->step_log, JSON_PRETTY_PRINT) }}</pre>
                        @endif
                        @if ($viewing->exception_report)
                            <h6>{{ __('Exception report') }}</h6>
                            <pre class="bg-light p-2 small">{{ json_encode($viewing->exception_report, JSON_PRETTY_PRINT) }}</pre>
                        @endif
                        @if (! $viewing->validation_report && ! $viewing->step_log && ! $viewing->exception_report)
                            <p class="text-body-secondary mb-0">{{ __('No report data recorded for this attempt.') }}</p>
                        @endif
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" wire:click="$set('viewingId', null)">{{ __('Close') }}</button>
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>
