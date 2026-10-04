<div>
    <div class="d-flex align-items-center gap-2 mb-4">
        <div class="flex-grow-1">
            <h4 class="mb-1">{{ __('Students') }}</h4>
            <p class="text-body-secondary mb-0">{{ __(':school\'s learner directory.', ['school' => $school->name]) }}</p>
        </div>
        <a href="{{ route('people.students.duplicates', $school) }}" class="btn btn-outline-secondary" wire:navigate>
            <i class="ri ri-fingerprint-line me-1"></i>{{ __('Duplicate scan') }}
        </a>
        <a href="{{ route('people.students.create', $school) }}" class="btn btn-primary" wire:navigate>
            <i class="ri ri-add-line me-1"></i>{{ __('New student') }}
        </a>
    </div>

    <x-data-table
        :columns="$this->tableColumns()"
        :rows="$students"
        :search="$search"
        :sort-column="$sortColumn"
        :sort-direction="$sortDirection"
        :per-page="$perPage"
        :column-filters="$columnFilters"
        :hidden-columns="$hiddenColumns"
        :per-page-options="$this->perPageOptions()"
        :with-actions="true"
    >
        @forelse ($students as $student)
            <tr wire:key="student-{{ $student->id }}">
                @if ($this->columnVisible('admission_number'))
                    <td>{{ $student->admission_number }}</td>
                @endif
                @if ($this->columnVisible('first_name'))
                    <td>{{ $student->first_name }}</td>
                @endif
                @if ($this->columnVisible('last_name'))
                    <td>{{ $student->last_name }}</td>
                @endif
                @if ($this->columnVisible('grade_level_id'))
                    <td>{{ $student->gradeLevel?->name }}@if ($student->schoolClass) <span class="text-body-secondary">— {{ $student->schoolClass->name }}</span>@endif</td>
                @endif
                @if ($this->columnVisible('enrolment_type'))
                    <td>{{ $student->enrolment_type }}</td>
                @endif
                @if ($this->columnVisible('residency'))
                    <td>{{ $student->residency }}</td>
                @endif
                @if ($this->columnVisible('status'))
                    <td><span class="badge {{ $student->status === 'active' ? 'text-bg-success' : 'text-bg-secondary' }}">{{ ucfirst($student->status) }}</span></td>
                @endif
                <td class="text-end">
                    <a href="{{ route('people.students.show', [$school, $student]) }}" class="btn btn-sm btn-outline-secondary" wire:navigate>{{ __('View') }}</a>
                </td>
            </tr>
        @empty
            <tr>
                <td colspan="8" class="text-center text-body-secondary py-4">{{ __('No students match these filters.') }}</td>
            </tr>
        @endforelse
    </x-data-table>
</div>
