<div>
    <h4 class="mb-1">{{ __('Teacher allocation matrix') }}</h4>
    <p class="text-body-secondary mb-4">{{ $school->name }} — {{ __('current term') }}</p>

    <div class="row g-4">
        <div class="col-md-5">
            <div class="card mb-4">
                <div class="card-header">{{ __('Allocate a teacher') }}</div>
                <div class="card-body">
                    <div class="row g-2">
                        <div class="col-12">
                            <select class="form-select form-select-sm @error('staffId') is-invalid @enderror" wire:model="staffId">
                                <option value="">{{ __('Select a teacher') }}</option>
                                @foreach ($teachingStaff as $member)
                                    <option value="{{ $member->id }}">{{ $member->fullName() }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-6">
                            <select class="form-select form-select-sm @error('subjectId') is-invalid @enderror" wire:model="subjectId">
                                <option value="">{{ __('Subject') }}</option>
                                @foreach ($subjects as $subject)
                                    <option value="{{ $subject->id }}">{{ $subject->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-6">
                            <select class="form-select form-select-sm @error('classId') is-invalid @enderror" wire:model="classId">
                                <option value="">{{ __('Class') }}</option>
                                @foreach ($classes as $class)
                                    <option value="{{ $class->id }}">{{ $class->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-6">
                            <input type="number" class="form-control form-control-sm @error('weeklyPeriods') is-invalid @enderror" wire:model="weeklyPeriods" placeholder="{{ __('Weekly periods') }}">
                        </div>
                        <div class="col-6">
                            <select class="form-select form-select-sm" wire:model="role">
                                <option value="teacher">{{ __('Teacher') }}</option>
                                <option value="assistant">{{ __('Assistant') }}</option>
                                <option value="moderator">{{ __('Moderator') }}</option>
                            </select>
                        </div>
                        <div class="col-12 form-check">
                            <input type="checkbox" class="form-check-input" id="isClassTeacher" wire:model="isClassTeacher">
                            <label class="form-check-label small" for="isClassTeacher">{{ __('Class teacher for this class') }}</label>
                        </div>
                        <div class="col-12 form-check">
                            <input type="checkbox" class="form-check-input" id="overrideCeiling" wire:model="overrideCeiling">
                            <label class="form-check-label small" for="overrideCeiling">{{ __('Override workload ceiling (BR-PPL-04-006)') }}</label>
                        </div>
                    </div>
                    <button type="button" class="btn btn-primary btn-sm mt-3" wire:click="allocate">{{ __('Allocate') }}</button>
                </div>
            </div>

            <div class="card">
                <div class="card-header">{{ __('Live workload by teacher') }}</div>
                <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        <thead><tr><th>{{ __('Teacher') }}</th><th>{{ __('Periods') }}</th><th>{{ __('Utilisation') }}</th></tr></thead>
                        <tbody>
                            @forelse ($workloads as $workload)
                                <tr>
                                    <td>{{ $workload->staff?->fullName() }}</td>
                                    <td>{{ $workload->teaching_periods }}</td>
                                    <td>
                                        {{ $workload->utilisation_percent ?? '—' }}%
                                        @if ($workload->is_overloaded) <span class="badge text-bg-danger">{{ __('Over') }}</span> @endif
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="3" class="text-center text-body-secondary py-3">{{ __('No allocations yet this term.') }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="col-md-7">
            <div class="card">
                <div class="card-header">{{ __('Active allocations this term') }}</div>
                <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        <thead><tr><th>{{ __('Teacher') }}</th><th>{{ __('Subject') }}</th><th>{{ __('Class') }}</th><th>{{ __('Periods') }}</th><th></th></tr></thead>
                        <tbody>
                            @forelse ($allocations as $allocation)
                                <tr wire:key="allocation-{{ $allocation->id }}">
                                    <td>{{ $allocation->staff?->fullName() }}</td>
                                    <td>{{ $allocation->subject?->name }}</td>
                                    <td>{{ $allocation->schoolClass?->name }}@if ($allocation->is_class_teacher) <span class="badge text-bg-primary ms-1">{{ __('CT') }}</span> @endif</td>
                                    <td>{{ $allocation->weekly_periods }}</td>
                                    <td class="text-end">
                                        <button type="button" class="btn btn-sm btn-outline-danger" wire:click="endAllocation({{ $allocation->id }})" wire:confirm="{{ __('End this allocation?') }}">{{ __('End') }}</button>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="text-center text-body-secondary py-3">{{ __('No active allocations this term.') }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
