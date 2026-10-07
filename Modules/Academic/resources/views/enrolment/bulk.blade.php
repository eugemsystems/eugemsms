<div>
    <h4 class="mb-1">{{ __('Bulk subject enrolment') }}</h4>
    <p class="text-body-secondary small">{{ __('Tick learners, then enrol them all in one subject. Every learner is validated independently — one being blocked never stops the rest.') }}</p>

    <div class="row g-2 mb-3 align-items-end" style="max-width:48rem">
        <div class="col-md-4">
            <label class="form-label small mb-0">{{ __('Grade level') }}</label>
            <select class="form-select form-select-sm" wire:model.live="gradeLevelId">
                <option value="">{{ __('Choose…') }}</option>
                @foreach ($gradeLevels as $level) <option value="{{ $level->id }}">{{ $level->name }}</option> @endforeach
            </select>
        </div>
        <div class="col-md-4">
            <label class="form-label small mb-0">{{ __('Class (optional)') }}</label>
            <select class="form-select form-select-sm" wire:model.live="classId">
                <option value="">{{ __('Whole level') }}</option>
                @foreach ($classes as $class) <option value="{{ $class->id }}">{{ $class->name }}</option> @endforeach
            </select>
        </div>
        <div class="col-md-4"><div class="form-check"><input type="checkbox" class="form-check-input" id="onlyNotEnrolled" wire:model.live="onlyNotEnrolled"><label class="form-check-label small" for="onlyNotEnrolled">{{ __('Only learners not already enrolled in this subject') }}</label></div></div>
    </div>

    <div class="row g-4">
        <div class="col-lg-7">
            <div class="d-flex justify-content-between align-items-center mb-2">
                <span class="small text-body-secondary">{{ __(':n learner(s) ticked', ['n' => count($selected)]) }}</span>
                <button type="button" class="btn btn-sm btn-outline-secondary" wire:click="selectAll">{{ __('Tick all shown') }}</button>
            </div>
            <div class="card"><div class="list-group list-group-flush" style="max-height:28rem;overflow:auto">
                @forelse ($learners as $learner)
                    <label class="list-group-item d-flex gap-2" wire:key="l-{{ $learner->id }}">
                        <input type="checkbox" class="form-check-input" value="{{ $learner->id }}" wire:model="selected">
                        <span>{{ $learner->last_name }}, {{ $learner->first_name }} <span class="text-body-secondary small">{{ $learner->admission_number }}</span></span>
                    </label>
                @empty
                    <div class="list-group-item text-body-secondary text-center">{{ $gradeLevelId === null ? __('Choose a grade level.') : __('No learners to show.') }}</div>
                @endforelse
            </div></div>
            @if ($outcomes)
                <div class="alert alert-light border mt-3 small mb-0">
                    <strong>{{ __('Outcome') }}</strong>
                    @foreach ($outcomes as $row)
                        <div class="{{ $row['enrolled'] ? 'text-success' : 'text-danger' }}">
                            {{ $names[$row['studentId']] ?? '#'.$row['studentId'] }} —
                            {{ $row['enrolled'] ? __('enrolled') : $row['message'] }}
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
        <div class="col-lg-5">
            <div class="card"><div class="card-header">{{ __('Enrol in subject') }}</div><div class="card-body">
                <select class="form-select mb-2" wire:model="subjectId">
                    <option value="">{{ __('Subject') }}</option>
                    @foreach ($subjects as $subject) <option value="{{ $subject->id }}">{{ $subject->name }}</option> @endforeach
                </select>
                <div class="mb-2">
                    <label class="form-label small mb-0">{{ __('Effective from') }}</label>
                    <input type="date" class="form-control" wire:model="effectiveFrom">
                </div>
                <input type="text" class="form-control mb-2" wire:model="reason" placeholder="{{ __('Reason (optional)') }}">
                <div class="form-check mb-2">
                    <input type="checkbox" class="form-check-input" id="acknowledgeWarnings" wire:model="acknowledgeWarnings">
                    <label class="form-check-label small" for="acknowledgeWarnings">{{ __('Acknowledge any rule warnings for the whole batch') }}</label>
                </div>
                @error('subjectId') <div class="text-danger small mb-2">{{ $message }}</div> @enderror
                <button type="button" class="btn btn-primary btn-sm" wire:click="enrol">{{ __('Enrol :n learner(s)', ['n' => count($selected)]) }}</button>
            </div></div>
        </div>
    </div>
</div>
