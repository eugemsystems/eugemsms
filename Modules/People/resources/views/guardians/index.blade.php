<div>
    <div class="d-flex align-items-center gap-2 mb-4">
        <div class="flex-grow-1">
            <h4 class="mb-1">{{ __('Guardians') }}</h4>
            <p class="text-body-secondary mb-0">{{ __(':school\'s guardian directory.', ['school' => $school->name]) }}</p>
        </div>
        <a href="{{ route('people.guardians.create', $school) }}" class="btn btn-primary" wire:navigate>
            <i class="ri ri-add-line me-1"></i>{{ __('New guardian') }}
        </a>
    </div>

    <x-data-table
        :columns="$this->tableColumns()"
        :rows="$guardians"
        :search="$search"
        :sort-column="$sortColumn"
        :sort-direction="$sortDirection"
        :per-page="$perPage"
        :column-filters="$columnFilters"
        :hidden-columns="$hiddenColumns"
        :per-page-options="$this->perPageOptions()"
        :with-actions="true"
    >
        @forelse ($guardians as $guardian)
            <tr wire:key="guardian-{{ $guardian->id }}">
                @if ($this->columnVisible('first_name'))
                    <td>{{ $guardian->first_name }}</td>
                @endif
                @if ($this->columnVisible('last_name'))
                    <td>{{ $guardian->last_name }}</td>
                @endif
                @if ($this->columnVisible('organisation_name'))
                    <td>{{ $guardian->organisation_name ?? '—' }}</td>
                @endif
                @if ($this->columnVisible('guardian_type'))
                    <td>{{ ucfirst($guardian->guardian_type) }}</td>
                @endif
                @if ($this->columnVisible('primary_phone'))
                    <td>{{ $guardian->primary_phone ?? '—' }}</td>
                @endif
                @if ($this->columnVisible('email'))
                    <td>{{ $guardian->email ?? '—' }}</td>
                @endif
                @if ($this->columnVisible('status'))
                    <td><span class="badge {{ $guardian->status === 'active' ? 'text-bg-success' : 'text-bg-secondary' }}">{{ ucfirst($guardian->status) }}</span></td>
                @endif
                <td class="text-end">
                    <a href="{{ route('people.guardians.show', [$school, $guardian]) }}" class="btn btn-sm btn-outline-secondary" wire:navigate>{{ __('View') }}</a>
                </td>
            </tr>
        @empty
            <tr>
                <td colspan="8" class="text-center text-body-secondary py-4">{{ __('No guardians match these filters.') }}</td>
            </tr>
        @endforelse
    </x-data-table>
</div>
