<div>
    <div class="d-flex align-items-center justify-content-between mb-4">
        <div>
            <h4 class="mb-1">{{ __('Period transition log') }}</h4>
            <p class="text-body-secondary mb-0">{{ __('Every state change for :school.', ['school' => $school->name]) }}</p>
        </div>
        <a href="{{ route('sessions.years', $school) }}" class="btn btn-outline-secondary" wire:navigate>
            <i class="ri ri-arrow-left-line me-1"></i>{{ __('Back to years') }}
        </a>
    </div>

    <x-data-table
        :columns="$this->tableColumns()"
        :rows="$transitions"
        :search="$search"
        :sort-column="$sortColumn"
        :sort-direction="$sortDirection"
        :per-page="$perPage"
        :column-filters="$columnFilters"
        :hidden-columns="$hiddenColumns"
        :per-page-options="$this->perPageOptions()"
    >
        @forelse ($transitions as $transition)
            <tr wire:key="transition-{{ $transition->id }}">
                <td>{{ $transition->term->name }}</td>
                @if ($this->columnVisible('period_type'))
                    <td class="text-capitalize">{{ $transition->period_type->value }}</td>
                @endif
                @if ($this->columnVisible('from_state'))
                    <td><span class="badge text-bg-light text-capitalize">{{ str_replace('_', ' ', $transition->from_state->value) }}</span></td>
                @endif
                @if ($this->columnVisible('to_state'))
                    <td><span class="badge text-bg-light text-capitalize">{{ str_replace('_', ' ', $transition->to_state->value) }}</span></td>
                @endif
                @if ($this->columnVisible('occurred_at'))
                    <td>{{ $transition->occurred_at->format('d M Y H:i') }}</td>
                @endif
                <td>
                    {{ $transition->performer->name }}
                    @if ($transition->approver)
                        <span class="text-body-secondary">&middot; {{ __('approved by') }} {{ $transition->approver->name }}</span>
                    @endif
                    @if ($transition->reason)
                        <div class="text-body-secondary small">{{ $transition->reason }}</div>
                    @endif
                </td>
            </tr>
        @empty
            <tr>
                <td colspan="6" class="text-center text-body-secondary py-4">
                    {{ __('No period transitions recorded yet.') }}
                </td>
            </tr>
        @endforelse
    </x-data-table>
</div>
