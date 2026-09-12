<div>
    <div class="d-flex align-items-center gap-2 mb-4">
        <a href="{{ route('audit.explorer', $school) }}" class="btn btn-icon btn-outline-secondary btn-sm" wire:navigate>
            <i class="ri ri-arrow-left-line"></i>
        </a>
        <div>
            <h4 class="mb-1">{{ __('Security events') }}</h4>
            <p class="text-body-secondary mb-0">{{ __('Triage queue — review each event to close it out.') }}</p>
        </div>
    </div>

    <x-data-table
        :columns="$this->tableColumns()"
        :rows="$events"
        :search="$search"
        :sort-column="$sortColumn"
        :sort-direction="$sortDirection"
        :per-page="$perPage"
        :column-filters="$columnFilters"
        :hidden-columns="$hiddenColumns"
        :per-page-options="$this->perPageOptions()"
    >
        @forelse ($events as $event)
            <tr wire:key="event-{{ $event->id }}">
                @if ($this->columnVisible('event_type'))
                    <td>{{ \Illuminate\Support\Str::headline($event->event_type) }}</td>
                @endif
                @if ($this->columnVisible('severity'))
                    <td>
                        <span class="badge text-bg-{{ match ($event->severity) { 'critical' => 'danger', 'warning' => 'warning', default => 'info' } }}">
                            {{ \Illuminate\Support\Str::headline($event->severity) }}
                        </span>
                    </td>
                @endif
                @if ($this->columnVisible('description'))
                    <td>{{ $event->description }}</td>
                @endif
                @if ($this->columnVisible('is_reviewed'))
                    <td>
                        @if ($event->is_reviewed)
                            <span class="badge text-bg-success">{{ __('Reviewed') }}</span>
                        @else
                            <span class="badge text-bg-secondary">{{ __('Unreviewed') }}</span>
                        @endif
                    </td>
                @endif
                @if ($this->columnVisible('occurred_at'))
                    <td>{{ $event->occurred_at->format('d M Y H:i') }}</td>
                @endif
                <td class="text-end">
                    @unless ($event->is_reviewed)
                        <button type="button" class="btn btn-sm btn-outline-primary" wire:click="openReviewModal({{ $event->id }})">
                            {{ __('Review') }}
                        </button>
                    @endunless
                </td>
            </tr>
        @empty
            <tr>
                <td colspan="6" class="text-center text-body-secondary py-4">{{ __('No security events recorded.') }}</td>
            </tr>
        @endforelse
    </x-data-table>

    @if ($reviewingEventId !== null)
        <div class="modal show d-block" tabindex="-1" style="background: rgba(0, 0, 0, .5);">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <form wire:submit="review">
                        <div class="modal-header">
                            <h5 class="modal-title">{{ __('Review security event') }}</h5>
                            <button type="button" class="btn-close" wire:click="$set('reviewingEventId', null)" aria-label="{{ __('Close') }}"></button>
                        </div>
                        <div class="modal-body">
                            <label class="form-label" for="reviewNotes">{{ __('Notes (optional)') }}</label>
                            <textarea class="form-control" id="reviewNotes" wire:model="reviewNotes" rows="3"></textarea>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-outline-secondary" wire:click="$set('reviewingEventId', null)">{{ __('Cancel') }}</button>
                            <button type="submit" class="btn btn-primary" wire:loading.attr="disabled">{{ __('Mark reviewed') }}</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endif
</div>
