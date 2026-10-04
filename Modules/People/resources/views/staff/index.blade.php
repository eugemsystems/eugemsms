<div>
    <div class="d-flex align-items-center gap-2 mb-4">
        <div class="flex-grow-1">
            <h4 class="mb-1">{{ __('Staff') }}</h4>
            <p class="text-body-secondary mb-0">{{ __(':school\'s staff directory.', ['school' => $school->name]) }}</p>
        </div>
        <a href="{{ route('people.establishment.index', $school) }}" class="btn btn-outline-secondary" wire:navigate>{{ __('Establishment') }}</a>
        <a href="{{ route('people.staff.compliance', $school) }}" class="btn btn-outline-secondary" wire:navigate>{{ __('Compliance') }}</a>
        <a href="{{ route('people.staff.create', $school) }}" class="btn btn-primary" wire:navigate>
            <i class="ri ri-add-line me-1"></i>{{ __('New staff') }}
        </a>
    </div>

    <x-data-table
        :columns="$this->tableColumns()"
        :rows="$staff"
        :search="$search"
        :sort-column="$sortColumn"
        :sort-direction="$sortDirection"
        :per-page="$perPage"
        :column-filters="$columnFilters"
        :hidden-columns="$hiddenColumns"
        :per-page-options="$this->perPageOptions()"
        :with-actions="true"
    >
        @forelse ($staff as $member)
            <tr wire:key="staff-{{ $member->id }}">
                @if ($this->columnVisible('staff_number'))
                    <td>{{ $member->staff_number }}</td>
                @endif
                @if ($this->columnVisible('first_name'))
                    <td>{{ $member->first_name }}</td>
                @endif
                @if ($this->columnVisible('last_name'))
                    <td>{{ $member->last_name }}</td>
                @endif
                @if ($this->columnVisible('staff_category'))
                    <td>{{ ucfirst($member->staff_category) }}</td>
                @endif
                @if ($this->columnVisible('department_id'))
                    <td>{{ $member->department?->name ?? '—' }}</td>
                @endif
                @if ($this->columnVisible('status'))
                    <td><span class="badge {{ $member->status === 'active' ? 'text-bg-success' : 'text-bg-secondary' }}">{{ str_replace('_', ' ', ucfirst($member->status)) }}</span></td>
                @endif
                <td class="text-end">
                    <a href="{{ route('people.staff.show', [$school, $member]) }}" class="btn btn-sm btn-outline-secondary" wire:navigate>{{ __('View') }}</a>
                </td>
            </tr>
        @empty
            <tr>
                <td colspan="7" class="text-center text-body-secondary py-4">{{ __('No staff match these filters.') }}</td>
            </tr>
        @endforelse
    </x-data-table>
</div>
