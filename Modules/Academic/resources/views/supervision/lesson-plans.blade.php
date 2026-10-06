<div>
    <div class="mb-4">
        <h4 class="mb-0">{{ __('Lesson plans') }}</h4>
        <p class="text-body-secondary small mb-0">{{ __('A plan linked to a scheme of work can only be submitted once that scheme is approved.') }}</p>
    </div>
    <div class="row g-4">
        <div class="col-xl-7">
            @if ($canReview)
                <div class="card mb-3"><div class="card-header">{{ __('Submitted for review') }}</div><ul class="list-group list-group-flush">
                    @forelse ($queue as $plan)
                        <li class="list-group-item" wire:key="q-{{ $plan->id }}">
                            <div class="d-flex justify-content-between"><div><strong>{{ $plan->topic }}</strong> <span class="small text-body-secondary">{{ $plan->lesson_date->format('d M Y') }} — {{ $teacherNames->get($plan->teacher_staff_id) }}</span>
                                @if ($plan->objectives) <div class="small">{{ $plan->objectives }}</div> @endif</div>
                                <button type="button" class="btn btn-sm btn-outline-primary" wire:click="startReview({{ $plan->id }})">{{ __('Review') }}</button></div>
                            @if ($reviewingId === $plan->id)
                                <textarea class="form-control form-control-sm mt-2" rows="2" wire:model="hodComments" placeholder="{{ __('Comments (optional)') }}"></textarea>
                                <button type="button" class="btn btn-sm btn-primary mt-2" wire:click="review">{{ __('Mark reviewed') }}</button>
                            @endif
                        </li>
                    @empty
                        <li class="list-group-item text-body-secondary">{{ __('Nothing waiting.') }}</li>
                    @endforelse
                </ul></div>
            @endif
            <div class="card"><div class="card-header">{{ __('My lesson plans') }}</div><ul class="list-group list-group-flush">
                @forelse ($mine as $plan)
                    <li class="list-group-item d-flex justify-content-between" wire:key="m-{{ $plan->id }}">
                        <div><strong>{{ $plan->topic }}</strong> <span class="badge text-bg-light border">{{ $plan->status }}</span><div class="small text-body-secondary">{{ $plan->lesson_date->format('d M Y') }}</div>
                            @if ($plan->hod_comments) <div class="small">{{ __('HOD:') }} {{ $plan->hod_comments }}</div> @endif</div>
                        @if ($plan->status === 'draft') <button type="button" class="btn btn-sm btn-primary align-self-start" wire:click="submit({{ $plan->id }})">{{ __('Submit') }}</button> @endif
                    </li>
                @empty
                    <li class="list-group-item text-body-secondary">{{ __('No lesson plans yet.') }}</li>
                @endforelse
            </ul></div>
        </div>
        @if ($canPlan)
            <div class="col-xl-5"><div class="card"><div class="card-header">{{ __('New lesson plan') }}</div><div class="card-body">
                <input type="date" class="form-control form-control-sm mb-2" wire:model="lessonDate">
                <input type="text" class="form-control form-control-sm mb-2" wire:model="topic" placeholder="{{ __('Topic') }}">
                @error('topic') <div class="text-danger small mb-2">{{ $message }}</div> @enderror
                <select class="form-select form-select-sm mb-2" wire:model="schemeId"><option value="">{{ __('Not linked to a scheme') }}</option>@foreach ($schemes as $scheme) <option value="{{ $scheme->id }}">{{ __('Scheme') }} #{{ $scheme->id }} ({{ $scheme->status }})</option> @endforeach</select>
                <textarea class="form-control form-control-sm mb-2" rows="2" wire:model="objectives" placeholder="{{ __('Objectives') }}"></textarea>
                <textarea class="form-control form-control-sm mb-2" rows="3" wire:model="activities" placeholder="{{ __('Activities') }}"></textarea>
                <input type="text" class="form-control form-control-sm mb-2" wire:model="resources" placeholder="{{ __('Resources needed') }}">
                <textarea class="form-control form-control-sm mb-2" rows="2" wire:model="differentiation" placeholder="{{ __('Differentiation notes') }}"></textarea>
                <button type="button" class="btn btn-primary btn-sm" wire:click="create">{{ __('Save draft') }}</button>
            </div></div></div>
        @endif
    </div>
</div>
