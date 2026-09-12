<div>
    <div class="d-flex align-items-center gap-2 mb-4 flex-wrap">
        <a href="{{ route('schools.index') }}" class="btn btn-icon btn-outline-secondary btn-sm" wire:navigate>
            <i class="ri ri-arrow-left-line"></i>
        </a>
        <div class="flex-grow-1">
            <h4 class="mb-1">{{ __('Notification log') }}</h4>
            <p class="text-body-secondary mb-0">{{ __('Every notification for :school.', ['school' => $school->name]) }}</p>
        </div>
        <a href="{{ route('notifications.failures', $school) }}" class="btn btn-outline-secondary" wire:navigate>{{ __('Failures') }}</a>
        <a href="{{ route('notifications.templates', $school) }}" class="btn btn-outline-secondary" wire:navigate>{{ __('Templates') }}</a>
        <a href="{{ route('notifications.budget', $school) }}" class="btn btn-outline-secondary" wire:navigate>{{ __('Budget') }}</a>
        <a href="{{ route('notifications.opt-outs', $school) }}" class="btn btn-outline-secondary" wire:navigate>{{ __('Opt-outs') }}</a>
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
    >
        @forelse ($notifications as $notification)
            <tr wire:key="notification-{{ $notification->id }}">
                @if ($this->columnVisible('notification_key'))
                    <td>{{ $notification->notification_key }}</td>
                @endif
                @if ($this->columnVisible('channel'))
                    <td>{{ \Illuminate\Support\Str::headline($notification->channel) }}</td>
                @endif
                @if ($this->columnVisible('recipient_address'))
                    <td>{{ $notification->recipient_address }}</td>
                @endif
                @if ($this->columnVisible('status'))
                    <td>
                        <span class="badge text-bg-{{ match ($notification->status) { 'sent', 'delivered', 'read' => 'success', 'failed', 'bounced' => 'danger', 'suppressed' => 'secondary', default => 'info' } }}">
                            {{ \Illuminate\Support\Str::headline($notification->status) }}
                        </span>
                    </td>
                @endif
                @if ($this->columnVisible('created_at'))
                    <td>{{ $notification->created_at->format('d M Y H:i') }}</td>
                @endif
            </tr>
        @empty
            <tr>
                <td colspan="5" class="text-center text-body-secondary py-4">{{ __('No notifications sent yet.') }}</td>
            </tr>
        @endforelse
    </x-data-table>
</div>
