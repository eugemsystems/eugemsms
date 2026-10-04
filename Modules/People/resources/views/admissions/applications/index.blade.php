<div>
    <div class="d-flex align-items-center gap-2 mb-4">
        <div class="flex-grow-1">
            <h4 class="mb-1">{{ __('Applications') }}</h4>
            <p class="text-body-secondary mb-0">{{ __(':school\'s admissions pipeline.', ['school' => $school->name]) }}</p>
        </div>
        <a href="{{ route('people.admissions.intakes.index', $school) }}" class="btn btn-outline-secondary" wire:navigate>{{ __('Intakes') }}</a>
        <a href="{{ route('people.admissions.applications.create', $school) }}" class="btn btn-primary" wire:navigate>
            <i class="ri ri-add-line me-1"></i>{{ __('New application') }}
        </a>
    </div>

    <x-data-table
        :columns="$this->tableColumns()"
        :rows="$applications"
        :search="$search"
        :sort-column="$sortColumn"
        :sort-direction="$sortDirection"
        :per-page="$perPage"
        :column-filters="$columnFilters"
        :hidden-columns="$hiddenColumns"
        :per-page-options="$this->perPageOptions()"
        :with-actions="true"
    >
        @forelse ($applications as $application)
            <tr wire:key="application-{{ $application->id }}">
                @if ($this->columnVisible('application_number'))
                    <td>{{ $application->application_number }}</td>
                @endif
                @if ($this->columnVisible('first_name'))
                    <td>{{ $application->first_name }}</td>
                @endif
                @if ($this->columnVisible('last_name'))
                    <td>{{ $application->last_name }}</td>
                @endif
                @if ($this->columnVisible('intake_id'))
                    <td>{{ $application->intake?->name }}</td>
                @endif
                @if ($this->columnVisible('status'))
                    <td><span class="badge text-bg-secondary">{{ str_replace('_', ' ', ucfirst($application->status)) }}</span></td>
                @endif
                @if ($this->columnVisible('priority_score'))
                    <td>{{ $application->priority_score ?? '—' }}</td>
                @endif
                @if ($this->columnVisible('submitted_at'))
                    <td>{{ $application->submitted_at?->format('d M Y') ?? '—' }}</td>
                @endif
                <td class="text-end">
                    <a href="{{ route('people.admissions.applications.show', [$school, $application]) }}" class="btn btn-sm btn-outline-secondary" wire:navigate>{{ __('View') }}</a>
                </td>
            </tr>
        @empty
            <tr>
                <td colspan="8" class="text-center text-body-secondary py-4">{{ __('No applications match these filters.') }}</td>
            </tr>
        @endforelse
    </x-data-table>
</div>
