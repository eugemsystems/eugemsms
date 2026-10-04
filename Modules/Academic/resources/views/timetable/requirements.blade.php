<div>
    <h4 class="mb-1">{{ __('Timetable requirements') }}</h4>
    <p class="text-body-secondary mb-4">{{ __('Built from PPL-04 teacher allocations for the active term, before generation runs.') }}</p>

    <div class="alert alert-info">
        {{ __('Default structure has :count teachable slot(s) per cycle. A requirement needing more periods than that is infeasible on its own.', ['count' => $teachableSlotCount]) }}
    </div>

    <div class="card">
        <div class="card-header">{{ __('Requirements') }}</div>
        <div class="table-responsive">
            <table class="table table-sm mb-0">
                <thead><tr><th>{{ __('Subject') }}</th><th>{{ __('Teacher') }}</th><th>{{ __('Periods/week') }}</th><th>{{ __('Scope') }}</th><th>{{ __('Feasibility') }}</th></tr></thead>
                <tbody>
                    @forelse ($requirements as $requirement)
                        <tr>
                            <td>{{ $subjectNames[$requirement->subjectId] ?? "#{$requirement->subjectId}" }}</td>
                            <td>{{ $staffNames[$requirement->staffId] ?? "#{$requirement->staffId}" }}</td>
                            <td>{{ $requirement->periodsPerWeek }}</td>
                            <td>{{ $requirement->classId !== null ? "Class #{$requirement->classId}" : "Group #{$requirement->teachingGroupId}" }}</td>
                            <td>
                                @if ($requirement->periodsPerWeek > $teachableSlotCount)
                                    <span class="badge text-bg-danger">{{ __('Infeasible — exceeds available slots') }}</span>
                                @else
                                    <span class="badge text-bg-success">{{ __('OK') }}</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="text-center text-body-secondary py-4">{{ __('No requirements for the active term — no active teacher allocations found.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
