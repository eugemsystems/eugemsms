<div>
    <h4 class="mb-1">{{ __('Bulk allocation') }}</h4>
    <p class="text-body-secondary small">{{ __('Tick learners, then place them in a class and/or a house. A learner in the wrong grade level, already there, or beyond a full class is skipped and listed below.') }}</p>

    <div class="row g-2 mb-3 align-items-end" style="max-width:48rem">
        <div class="col-md-4">
            <label class="form-label small mb-0">{{ __('Grade level') }}</label>
            <select class="form-select form-select-sm" wire:model.live="gradeLevelId">
                <option value="">{{ __('Choose…') }}</option>
                @foreach ($gradeLevels as $level) <option value="{{ $level->id }}">{{ $level->name }}</option> @endforeach
            </select>
        </div>
        <div class="col-md-4"><div class="form-check"><input type="checkbox" class="form-check-input" id="onlyUnallocated" wire:model.live="onlyUnallocated"><label class="form-check-label small" for="onlyUnallocated">{{ __('Only learners with no class this term') }}</label></div></div>
        <div class="col-md-4"><button type="button" class="btn btn-sm btn-outline-secondary" wire:click="selectAll">{{ __('Tick all shown') }}</button></div>
    </div>

    <div class="row g-4">
        <div class="col-lg-7">
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
            @if ($skipped)
                <div class="alert alert-warning mt-3 small mb-0">
                    <strong>{{ __('Skipped') }}</strong>
                    @foreach ($skipped as $row)<div>{{ $names[$row['student_id']] ?? '#'.$row['student_id'] }} — {{ $row['reason'] }}</div>@endforeach
                </div>
            @endif
        </div>
        <div class="col-lg-5">
            <div class="card mb-3"><div class="card-header">{{ __('Place in a class') }}</div><div class="card-body">
                <select class="form-select mb-2" wire:model="classId">
                    <option value="">{{ __('Class') }}</option>
                    @foreach ($classes as $class) <option value="{{ $class->id }}">{{ $class->name }} ({{ __('capacity :n', ['n' => $class->capacity]) }})</option> @endforeach
                </select>
                @error('classId') <div class="text-danger small mb-2">{{ $message }}</div> @enderror
                @error('studentIds') <div class="text-danger small mb-2">{{ $message }}</div> @enderror
                <button type="button" class="btn btn-primary btn-sm" wire:click="applyClass">{{ __('Place :n learner(s)', ['n' => count($selected)]) }}</button>
            </div></div>
            <div class="card"><div class="card-header">{{ __('Place in a house') }}</div><div class="card-body">
                <select class="form-select mb-2" wire:model="houseId">
                    <option value="">{{ __('House') }}</option>
                    @foreach ($houses as $house) <option value="{{ $house->id }}">{{ $house->name }}</option> @endforeach
                </select>
                @error('houseId') <div class="text-danger small mb-2">{{ $message }}</div> @enderror
                <button type="button" class="btn btn-primary btn-sm" wire:click="applyHouse">{{ __('Place :n learner(s)', ['n' => count($selected)]) }}</button>
            </div></div>
        </div>
    </div>
</div>
