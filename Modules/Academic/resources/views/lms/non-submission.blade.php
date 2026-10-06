<div>
    <div class="d-flex align-items-center gap-2 mb-4">
        <a href="{{ route('academic.lms.space', [$school, $assignment->course_space_id]) }}" class="btn btn-sm btn-outline-secondary" wire:navigate><i class="ri ri-arrow-left-line"></i></a>
        <div><h4 class="mb-0">{{ __('Not yet submitted') }} — {{ $assignment->title }}</h4><p class="text-body-secondary small mb-0">{{ __('Appears once the assignment is past its due date.') }}</p></div>
        <button type="button" class="btn btn-primary btn-sm ms-auto" wire:click="chase" wire:confirm="{{ __('Remind every learner on this list?') }}" @disabled($students->isEmpty())>{{ __('Remind all') }}</button>
    </div>
    <div class="card"><ul class="list-group list-group-flush">
        @forelse ($students as $student) <li class="list-group-item" wire:key="ns-{{ $student->id }}">{{ $student->fullName() }} <span class="text-body-secondary small">{{ $student->admission_number }}</span></li>
        @empty <li class="list-group-item text-body-secondary">{{ __('Nobody is outstanding.') }}</li> @endforelse
    </ul></div>
</div>
