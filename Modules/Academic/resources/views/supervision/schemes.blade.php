<div>
    <div class="mb-4">
        <h4 class="mb-0">{{ __('Schemes of work') }}</h4>
        <p class="text-body-secondary small mb-0">{{ __('One scheme per subject, grade level and term. A linked lesson plan can only be submitted once its scheme is approved.') }}</p>
    </div>
    <div class="row g-4">
        <div class="col-xl-7">
            @if ($canApprove)
                <div class="card mb-3"><div class="card-header">{{ __('Awaiting your decision') }}</div>
                    <ul class="list-group list-group-flush">
                        @forelse ($queue as $scheme)
                            <li class="list-group-item" wire:key="q-{{ $scheme->id }}">
                                <div class="d-flex justify-content-between align-items-start gap-2">
                                    <div><strong>{{ $subjectNames->get($scheme->subject_id) }}</strong> · {{ $gradeNames->get($scheme->grade_level_id) }} <span class="small text-body-secondary">— {{ $teacherNames->get($scheme->teacher_staff_id) }}</span>
                                        <ol class="small mb-0 mt-1">@foreach ($scheme->planned_topics as $topic) <li value="{{ $topic['week'] }}">{{ __('Week') }} {{ $topic['week'] }}: {{ $topic['topic'] }}</li> @endforeach</ol></div>
                                    <div class="text-nowrap"><button type="button" class="btn btn-sm btn-primary" wire:click="approve({{ $scheme->id }})">{{ __('Approve') }}</button> <button type="button" class="btn btn-sm btn-outline-secondary" wire:click="startReturn({{ $scheme->id }})">{{ __('Return') }}</button></div>
                                </div>
                                @if ($returningId === $scheme->id)
                                    <textarea class="form-control form-control-sm mt-2" rows="2" wire:model="returnComment" placeholder="{{ __('What needs to change?') }}"></textarea>
                                    <button type="button" class="btn btn-sm btn-secondary mt-2" wire:click="sendBack">{{ __('Send back') }}</button>
                                @endif
                            </li>
                        @empty
                            <li class="list-group-item text-body-secondary">{{ __('Nothing waiting.') }}</li>
                        @endforelse
                    </ul>
                </div>
            @endif
            <div class="card"><div class="card-header">{{ __('My schemes') }}</div>
                <ul class="list-group list-group-flush">
                    @forelse ($mine as $scheme)
                        <li class="list-group-item" wire:key="m-{{ $scheme->id }}">
                            <div class="d-flex justify-content-between"><div><strong>{{ $subjectNames->get($scheme->subject_id) }}</strong> · {{ $gradeNames->get($scheme->grade_level_id) }} <span class="badge text-bg-light border">{{ $scheme->status }}</span></div>
                                <div class="text-nowrap">
                                    @if (in_array($scheme->status, ['draft', 'returned'])) <button type="button" class="btn btn-sm btn-outline-secondary" wire:click="edit({{ $scheme->id }})">{{ __('Edit') }}</button> <button type="button" class="btn btn-sm btn-primary" wire:click="submit({{ $scheme->id }})">{{ __('Submit') }}</button> @endif
                                </div></div>
                            @if ($scheme->status === 'returned' && $scheme->review_comments) <div class="small text-danger mt-1">{{ $scheme->review_comments }}</div> @endif
                            <div class="small text-body-secondary">{{ count($scheme->planned_topics) }} {{ __('topics') }}</div>
                        </li>
                    @empty
                        <li class="list-group-item text-body-secondary">{{ __('You have no schemes yet.') }}</li>
                    @endforelse
                </ul>
            </div>
        </div>
        @if ($canPlan)
            <div class="col-xl-5"><div class="card"><div class="card-header">{{ $editingId ? __('Revise scheme') : __('New scheme (this term)') }}</div><div class="card-body">
                @unless ($editingId)
                    <select class="form-select form-select-sm mb-2" wire:model="subjectId"><option value="">{{ __('Subject…') }}</option>@foreach ($subjects as $subject) <option value="{{ $subject->id }}">{{ $subject->name }}</option> @endforeach</select>
                    <select class="form-select form-select-sm mb-2" wire:model="gradeLevelId"><option value="">{{ __('Grade level…') }}</option>@foreach ($gradeLevels as $level) <option value="{{ $level->id }}">{{ $level->name }}</option> @endforeach</select>
                @endunless
                <textarea class="form-control form-control-sm mb-2" rows="8" wire:model="topicsText" placeholder="{{ __('One topic per line: week | topic | objectives | resources') }}"></textarea>
                @error('topicsText') <div class="text-danger small mb-2">{{ $message }}</div> @enderror
                <button type="button" class="btn btn-primary btn-sm" wire:click="save">{{ __('Save') }}</button>
                @if ($editingId) <button type="button" class="btn btn-link btn-sm" wire:click="$set('editingId', null)">{{ __('Cancel') }}</button> @endif
            </div></div></div>
        @endif
    </div>
</div>
