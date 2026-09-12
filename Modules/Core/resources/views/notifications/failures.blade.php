<div>
    <div class="d-flex align-items-center gap-2 mb-4 flex-wrap">
        <a href="{{ route('notifications.log', $school) }}" class="btn btn-icon btn-outline-secondary btn-sm" wire:navigate>
            <i class="ri ri-arrow-left-line"></i>
        </a>
        <div class="flex-grow-1">
            <h4 class="mb-1">{{ __('Notification failures') }}</h4>
            <p class="text-body-secondary mb-0">{{ __('Failed and bounced sends.') }}</p>
        </div>
        @if (! empty($selected))
            <button type="button" class="btn btn-primary" wire:click="retrySelected" wire:loading.attr="disabled">
                {{ __('Retry selected (:count)', ['count' => count($selected)]) }}
            </button>
        @endif
    </div>

    <x-data-table
        :columns="$this->tableColumns()"
        :rows="$notifications"
        :search="$search"
        :sort-column="$sortColumn"
        :sort-direction="$sortDirection"
        :per-page="$perPage"
        :column-filters="$columnFilters"
        :hidden-columns="$hiddenColumns"
        :per-page-options="$this->perPageOptions()"
        :with-selection="true"
    >
        @forelse ($notifications as $notification)
            <tr wire:key="failure-{{ $notification->id }}">
                <td style="width: 2rem;">
                    <input type="checkbox" class="form-check-input" wire:model="selected" value="{{ $notification->id }}">
                </td>
                @if ($this->columnVisible('notification_key'))
                    <td>{{ $notification->notification_key }}</td>
                @endif
                @if ($this->columnVisible('channel'))
                    <td>{{ \Illuminate\Support\Str::headline($notification->channel) }}</td>
                @endif
                @if ($this->columnVisible('recipient_address'))
                    <td>{{ $notification->recipient_address }}</td>
                @endif
                @if ($this->columnVisible('error_code'))
                    <td>{{ $notification->error_code }}</td>
                @endif
                @if ($this->columnVisible('attempt_count'))
                    <td>{{ $notification->attempt_count }}</td>
                @endif
                @if ($this->columnVisible('created_at'))
                    <td>{{ $notification->created_at->format('d M Y H:i') }}</td>
                @endif
                <td class="text-end">
                    <button type="button" class="btn btn-sm btn-outline-primary" wire:click="retry({{ $notification->id }})">
                        {{ __('Retry') }}
                    </button>
                </td>
            </tr>
        @empty
            <tr>
                <td colspan="8" class="text-center text-body-secondary py-4">{{ __('No failures — everything is sending cleanly.') }}</td>
            </tr>
        @endforelse
    </x-data-table>
</div>
