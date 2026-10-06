<div>
    <div class="mb-4">
        <h4 class="mb-0">{{ __('Observe a lesson') }}</h4>
        <p class="text-body-secondary small mb-0">{{ __('Score every criterion. The teacher can comment on the record afterwards but cannot change your scores.') }}</p>
    </div>
    <div class="row g-4">
        @if ($canObserve)
            <div class="col-lg-7"><div class="card"><div class="card-body">
                <select class="form-select mb-2" wire:model="observedStaffId"><option value="">{{ __('Teacher observed…') }}</option>@foreach ($staff as $member) <option value="{{ $member->id }}">{{ $member->fullName() }}</option> @endforeach</select>
                @error('observedStaffId') <div class="text-danger small mb-2">{{ $message }}</div> @enderror
                <div class="row g-2 mb-2">
                    <div class="col-sm-6"><input type="datetime-local" class="form-control" wire:model="observedAt"></div>
                    <div class="col-sm-6"><input type="text" class="form-control" wire:model="classObserved" placeholder="{{ __('Class observed') }}" maxlength="80"></div>
                </div>
                <select class="form-select mb-2" wire:model="subjectId"><option value="">{{ __('Subject (optional)') }}</option>@foreach ($subjects as $subject) <option value="{{ $subject->id }}">{{ $subject->name }}</option> @endforeach</select>
                <select class="form-select mb-3" wire:model.live="rubricId"><option value="">{{ __('Rubric…') }}</option>@foreach ($rubrics as $r) <option value="{{ $r->id }}">{{ $r->name }}</option> @endforeach</select>
                @if ($rubric)
                    @foreach (array_values($rubric->criteria) as $position => $criterion)
                        <div class="mb-3" wire:key="c-{{ $rubric->id }}-{{ $position }}">
                            <div class="fw-semibold mb-1">{{ $criterion['criterion'] }}</div>
                            <div class="btn-group flex-wrap" role="group">
                                @foreach ($criterion['descriptor_levels'] as $levelIndex => $level)
                                    <input type="radio" class="btn-check" id="sc-{{ $position }}-{{ $levelIndex }}" value="{{ $level }}" wire:model="scores.{{ $position }}">
                                    <label class="btn btn-outline-secondary" for="sc-{{ $position }}-{{ $levelIndex }}">{{ $level }}</label>
                                @endforeach
                            </div>
                        </div>
                    @endforeach
                @endif
                <textarea class="form-control mb-2" rows="2" wire:model="strengths" placeholder="{{ __('Strengths noted') }}"></textarea>
                <textarea class="form-control mb-2" rows="2" wire:model="areas" placeholder="{{ __('Areas for development') }}"></textarea>
                <div class="row g-2 mb-3">
                    <div class="col-sm-6"><select class="form-select" wire:model="rating"><option value="">{{ __('Overall rating…') }}</option>@foreach (['outstanding', 'good', 'needs_improvement', 'inadequate'] as $r) <option value="{{ $r }}">{{ __(ucfirst(str_replace('_', ' ', $r))) }}</option> @endforeach</select></div>
                    <div class="col-sm-6"><select class="form-select" wire:model="followUpId"><option value="">{{ __('Not a follow-up') }}</option>@foreach ($earlier as $previous) <option value="{{ $previous->id }}">{{ __('Follow-up to') }} {{ $previous->observed_at->format('d M Y') }}</option> @endforeach</select></div>
                </div>
                <button type="button" class="btn btn-primary" wire:click="record">{{ __('Save observation') }}</button>
            </div></div></div>
        @endif
        @if ($canManageRubrics)
            <div class="col-lg-5"><div class="card"><div class="card-header">{{ __('New rubric') }}</div><div class="card-body">
                <input type="text" class="form-control form-control-sm mb-2" wire:model="rubricName" placeholder="{{ __('Rubric name') }}">
                @error('rubricName') <div class="text-danger small mb-2">{{ $message }}</div> @enderror
                <textarea class="form-control form-control-sm mb-2" rows="6" wire:model="rubricCriteria" placeholder="{{ __('One criterion per line: Planning: emerging, proficient, advanced') }}"></textarea>
                <button type="button" class="btn btn-primary btn-sm" wire:click="createRubric">{{ __('Create rubric') }}</button>
            </div></div></div>
        @endif
    </div>
</div>
