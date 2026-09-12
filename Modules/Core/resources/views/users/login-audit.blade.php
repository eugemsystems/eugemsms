<div>
    <div class="mb-4">
        <h4 class="mb-1">{{ __('Login audit') }}</h4>
        <p class="text-body-secondary mb-0">{{ __('Every login attempt, successful or not, for your tenant (BR-CORE-05-007).') }}</p>
    </div>

    <x-data-table
        :columns="$this->tableColumns()"
        :rows="$attempts"
        :search="$search"
        :sort-column="$sortColumn"
        :sort-direction="$sortDirection"
        :per-page="$perPage"
        :column-filters="$columnFilters"
        :hidden-columns="$hiddenColumns"
        :per-page-options="$this->perPageOptions()"
        :with-actions="false"
    >
        @forelse ($attempts as $attempt)
            <tr wire:key="login-attempt-{{ $attempt->id }}">
                @if ($this->columnVisible('identifier'))
                    <td>{{ $attempt->identifier }}</td>
                @endif
                @if ($this->columnVisible('user'))
                    <td>{{ $attempt->user?->name ?? '—' }}</td>
                @endif
                @if ($this->columnVisible('guard'))
                    <td class="text-uppercase">{{ $attempt->guard }}</td>
                @endif
                @if ($this->columnVisible('was_successful'))
                    <td>
                        @if ($attempt->was_successful)
                            <span class="badge text-bg-success">{{ __('Success') }}</span>
                        @else
                            <span class="badge text-bg-danger">{{ __('Failure') }}</span>
                        @endif
                    </td>
                @endif
                @if ($this->columnVisible('failure_reason'))
                    <td>{{ $attempt->failure_reason ?? '—' }}</td>
                @endif
                @if ($this->columnVisible('ip_address'))
                    <td>{{ $attempt->ip_address ?? '—' }}</td>
                @endif
                @if ($this->columnVisible('attempted_at'))
                    <td>{{ $attempt->attempted_at->format('d M Y H:i') }}</td>
                @endif
            </tr>
        @empty
            <tr>
                <td colspan="7" class="text-center text-body-secondary py-4">{{ __('No login attempts recorded yet.') }}</td>
            </tr>
        @endforelse
    </x-data-table>
</div>
