<div>
    <div class="d-flex align-items-center gap-2 mb-4">
        <a href="{{ route('comms.complaints.queue', $school) }}" class="btn btn-icon btn-outline-secondary btn-sm" wire:navigate>
            <i class="ri ri-arrow-left-line"></i>
        </a>
        <div>
            <h4 class="mb-0">{{ $complaint->complaint_number }}</h4>
            <span class="badge bg-label-secondary">{{ $complaint->status }}</span>
            <span class="text-body-secondary small">{{ $complaint->category?->name }}</span>
        </div>
    </div>

    @if ($routed)
        <div class="alert alert-info">{{ __('This complaint has been referred to the safeguarding team. Its details are held in the safeguarding case and are not shown here, and it cannot be worked from the complaint queue.') }}</div>
    @else
        <div class="row g-4">
            <div class="col-lg-7">
                <div class="card mb-4">
                    <div class="card-header">{{ $complaint->subject }}</div>
                    <div class="card-body small">
                        <p style="white-space: pre-wrap">{{ $complaint->description }}</p>
                        <div class="text-body-secondary">
                            {{ __('Severity') }}: {{ $complaint->severity }} ·
                            {{ __('Raised by') }}: {{ $complaint->raised_by_type }} ·
                            {{ __('Response due') }}: {{ $complaint->sla_due_at->toDayDateTimeString() }}
                            @if ($complaint->isBreached()) <span class="badge bg-label-danger">{{ __('overdue') }}</span> @endif
                            @if ($student) · {{ __('Learner') }}: {{ $student->first_name }} {{ $student->last_name }} @endif
                        </div>
                        @if ($complaint->resolution) <div class="alert alert-success mt-3 mb-0"><strong>{{ __('Resolution') }}:</strong> {{ $complaint->resolution }}</div> @endif
                    </div>
                </div>

                <div class="card mb-4">
                    <div class="card-header">{{ __('Thread the raiser can see') }}</div>
                    <ul class="list-group list-group-flush small">
                        @forelse ($raiserThread as $update)
                            <li class="list-group-item" wire:key="rt-{{ $update->id }}">{{ $update->content }} <span class="text-body-secondary">— {{ $update->update_type }}, {{ $update->posted_at->diffForHumans() }}</span></li>
                        @empty
                            <li class="list-group-item text-body-secondary">{{ __('Nothing yet.') }}</li>
                        @endforelse
                    </ul>
                </div>

                <div class="card border-warning">
                    <div class="card-header bg-label-warning">{{ __('Internal notes — never shown to the raiser') }}</div>
                    <ul class="list-group list-group-flush small">
                        @forelse ($internalNotes as $update)
                            <li class="list-group-item" wire:key="in-{{ $update->id }}">{{ $update->content }} <span class="text-body-secondary">— {{ $update->update_type }}, {{ $update->posted_at->diffForHumans() }}</span></li>
                        @empty
                            <li class="list-group-item text-body-secondary">{{ __('No internal notes.') }}</li>
                        @endforelse
                    </ul>
                </div>
            </div>

            <div class="col-lg-5">
                <div class="card mb-4">
                    <div class="card-header">{{ __('Add to the case') }}</div>
                    <div class="card-body">
                        <textarea class="form-control mb-2" rows="3" wire:model="note" placeholder="{{ __('Note or reply') }}"></textarea>
                        @error('note') <div class="text-danger small mb-2">{{ $message }}</div> @enderror
                        <div class="form-check mb-2"><input class="form-check-input" type="checkbox" id="cm-vis" wire:model="visibleToRaiser"><label class="form-check-label small" for="cm-vis">{{ __('Visible to the raiser (otherwise an internal note)') }}</label></div>
                        <button type="button" class="btn btn-primary btn-sm" wire:click="post">{{ __('Save') }}</button>
                    </div>
                </div>

                @if ($canAssign)
                    <div class="card mb-4">
                        <div class="card-header">{{ __('Assignee') }}: {{ $assignee?->fullName() ?? __('unassigned') }}</div>
                        <div class="card-body">
                            <select class="form-select mb-2" wire:model="assigneeId">
                                <option value="">{{ __('Assign to…') }}</option>
                                @foreach ($staff as $member) <option value="{{ $member->id }}">{{ $member->fullName() }}</option> @endforeach
                            </select>
                            @error('assigneeId') <div class="text-danger small mb-2">{{ $message }}</div> @enderror
                            <button type="button" class="btn btn-outline-primary btn-sm" wire:click="assign">{{ __('Assign') }}</button>
                        </div>
                    </div>
                @endif

                @unless ($finished)
                    <div class="card">
                        <div class="card-header">{{ __('Progress') }}</div>
                        <div class="card-body">
                            <div class="d-flex flex-wrap gap-1 mb-3">
                                <button type="button" class="btn btn-sm btn-outline-secondary" wire:click="setStatus('acknowledged')">{{ __('Acknowledge') }}</button>
                                <button type="button" class="btn btn-sm btn-outline-secondary" wire:click="setStatus('investigating')">{{ __('Investigating') }}</button>
                                <button type="button" class="btn btn-sm btn-outline-warning" wire:click="setStatus('escalated')">{{ __('Escalate') }}</button>
                                <button type="button" class="btn btn-sm btn-outline-danger" wire:click="setStatus('closed')" wire:confirm="{{ __('Close without a resolution?') }}">{{ __('Close') }}</button>
                            </div>
                            <textarea class="form-control mb-2" rows="3" wire:model="resolution" placeholder="{{ __('Resolution — what was done') }}"></textarea>
                            @error('resolution') <div class="text-danger small mb-2">{{ $message }}</div> @enderror
                            <button type="button" class="btn btn-success btn-sm" wire:click="resolve">{{ __('Mark resolved') }}</button>
                        </div>
                    </div>
                @endunless
            </div>
        </div>
    @endif
</div>
