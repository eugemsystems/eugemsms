<div>
    <div class="d-flex align-items-center justify-content-between mb-4">
        <div>
            <h4 class="mb-1">{{ __('Period snapshots') }}</h4>
            <p class="text-body-secondary mb-0">{{ __('Tamper-evident, append-only — for :school.', ['school' => $school->name]) }}</p>
        </div>
        <a href="{{ route('sessions.years', $school) }}" class="btn btn-outline-secondary" wire:navigate>
            <i class="ri ri-arrow-left-line me-1"></i>{{ __('Back to years') }}
        </a>
    </div>

    <x-data-table
        :columns="$this->tableColumns()"
        :rows="$snapshots"
        :search="$search"
        :sort-column="$sortColumn"
        :sort-direction="$sortDirection"
        :per-page="$perPage"
        :column-filters="$columnFilters"
        :hidden-columns="$hiddenColumns"
        :per-page-options="$this->perPageOptions()"
    >
        @forelse ($snapshots as $snapshot)
            <tr wire:key="snapshot-{{ $snapshot->id }}">
                @if ($this->columnVisible('term'))
                    <td>{{ $snapshot->term->name }} <span class="text-body-secondary">({{ $snapshot->academicYear->name }})</span></td>
                @endif
                @if ($this->columnVisible('snapshot_type'))
                    <td class="text-capitalize">{{ str_replace('_', ' ', $snapshot->snapshot_type) }}</td>
                @endif
                @if ($this->columnVisible('taken_at'))
                    <td>{{ $snapshot->taken_at->format('d M Y H:i') }}</td>
                @endif
                @if ($this->columnVisible('taken_by'))
                    <td>{{ $snapshot->takenBy?->name ?? '—' }}</td>
                @endif
                <td class="text-end">
                    <div class="d-flex justify-content-end gap-1">
                        <button type="button" class="btn btn-icon btn-sm btn-outline-secondary" wire:click="view({{ $snapshot->id }})" title="{{ __('View') }}" aria-label="{{ __('View') }}">
                            <i class="icon-base ri ri-eye-line icon-22px"></i>
                        </button>
                    </div>
                </td>
            </tr>
        @empty
            <tr>
                <td colspan="5" class="text-center text-body-secondary py-4">
                    {{ __('No snapshots have been taken yet.') }}
                </td>
            </tr>
        @endforelse
    </x-data-table>

    @if ($viewing)
        <div class="modal show d-block" tabindex="-1" style="background: rgba(0, 0, 0, .5);">
            <div class="modal-dialog modal-dialog-centered modal-lg">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">{{ __('Snapshot') }} #{{ $viewing->id }}</h5>
                        <button type="button" class="btn-close" wire:click="$set('viewingId', null)" aria-label="{{ __('Close') }}"></button>
                    </div>
                    <div class="modal-body" style="max-height: 60vh; overflow-y: auto;">
                        <dl class="row">
                            <dt class="col-4">{{ __('Payload hash') }}</dt>
                            <dd class="col-8 text-break font-monospace small">{{ $viewing->payload_hash }}</dd>
                            <dt class="col-4">{{ __('Previous hash') }}</dt>
                            <dd class="col-8 text-break font-monospace small">{{ $viewing->previous_hash ?? __('(none — first snapshot)') }}</dd>
                        </dl>
                        <h6>{{ __('Row counts') }}</h6>
                        <pre class="bg-light p-2 small">{{ json_encode($viewing->row_counts, JSON_PRETTY_PRINT) }}</pre>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" wire:click="$set('viewingId', null)">{{ __('Close') }}</button>
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>
