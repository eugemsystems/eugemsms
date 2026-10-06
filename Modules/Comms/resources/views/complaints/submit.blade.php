<div>
    <h4 class="mb-1">{{ __('Raise a complaint') }}</h4>
    <p class="text-body-secondary small">{{ __('Tell us what went wrong. You will see the response deadline straight away and can follow progress below. If you think a child may be at risk, tick the safeguarding box — the report then goes to the safeguarding team, not the ordinary queue.') }}</p>

    @if ($submittedNumber)
        <div class="alert alert-success small">
            {{ __('Complaint :number received.', ['number' => $submittedNumber]) }}
            @if ($submittedDue) {{ __('We will respond by :due.', ['due' => $submittedDue]) }} @else {{ __('It has been referred to the safeguarding team.') }} @endif
        </div>
    @endif

    <div class="row g-4">
        <div class="col-lg-6">
            <div class="card">
                <div class="card-body">
                    <select class="form-select mb-2" wire:model="categoryId">
                        <option value="">{{ __('Category…') }}</option>
                        @foreach ($categories as $category) <option value="{{ $category->id }}">{{ $category->name }}{{ $category->is_safeguarding_trigger ? '' : ' — '.__('reply within :h h', ['h' => $category->sla_hours]) }}</option> @endforeach
                    </select>
                    @error('categoryId') <div class="text-danger small mb-2">{{ $message }}</div> @enderror
                    <input type="text" class="form-control mb-2" wire:model="subject" placeholder="{{ __('Subject') }}">
                    @error('subject') <div class="text-danger small mb-2">{{ $message }}</div> @enderror
                    <textarea class="form-control mb-2" rows="5" wire:model="description" placeholder="{{ __('What happened?') }}"></textarea>
                    @error('description') <div class="text-danger small mb-2">{{ $message }}</div> @enderror
                    <div class="row g-2 mb-2">
                        <div class="col-6">
                            <select class="form-select" wire:model="severity">
                                <option value="low">{{ __('Low') }}</option>
                                <option value="medium">{{ __('Medium') }}</option>
                                <option value="high">{{ __('High') }}</option>
                            </select>
                        </div>
                        <div class="col-6"><input type="text" class="form-control" wire:model="admissionNumber" placeholder="{{ __('Learner admission no. (optional)') }}"></div>
                    </div>
                    @error('admissionNumber') <div class="text-danger small mb-2">{{ $message }}</div> @enderror
                    <div class="form-check"><input class="form-check-input" type="checkbox" id="c-sg" wire:model="suspectedSafeguardingConcern"><label class="form-check-label small" for="c-sg">{{ __('I think a child may be at risk of harm (refer to safeguarding)') }}</label></div>
                    <div class="form-check mb-3"><input class="form-check-input" type="checkbox" id="c-anon" wire:model="anonymous"><label class="form-check-label small" for="c-anon">{{ __('Submit anonymously — my name is not recorded') }}</label></div>
                    <button type="button" class="btn btn-primary btn-sm" wire:click="submit">{{ __('Submit complaint') }}</button>
                </div>
            </div>
        </div>

        <div class="col-lg-6">
            <div class="card">
                <div class="card-header">{{ __('My complaints') }}</div>
                <div class="card-body small">
                    @forelse ($mine as $complaint)
                        <div class="border-bottom pb-2 mb-2" wire:key="mine-{{ $complaint->id }}">
                            @if ($complaint->isRoutedToSafeguarding())
                                <strong>{{ $complaint->complaint_number }}</strong> <span class="badge bg-label-info">{{ __('referred to safeguarding') }}</span>
                            @else
                                <strong>{{ $complaint->complaint_number }}</strong> — {{ $complaint->subject }}
                                <span class="badge bg-label-secondary">{{ $complaint->status }}</span>
                                <div class="text-body-secondary">{{ __('Response due :due', ['due' => $complaint->sla_due_at->toDayDateTimeString()]) }}</div>
                                @foreach ($threads[$complaint->id] ?? [] as $update)
                                    <div class="ps-2 border-start mt-1">{{ $update->content }} <span class="text-body-secondary">{{ $update->posted_at->diffForHumans() }}</span></div>
                                @endforeach
                                @if (in_array($complaint->status, ['resolved', 'closed'], true))
                                    @if ($complaint->satisfaction_rating !== null)
                                        <div class="mt-1">{{ __('You rated this :rating/5.', ['rating' => $complaint->satisfaction_rating]) }}</div>
                                    @else
                                        <div class="mt-1">{{ __('How well was this handled?') }}
                                            @for ($i = 1; $i <= 5; $i++)
                                                <button type="button" class="btn btn-sm btn-outline-secondary py-0" wire:click="rate({{ $complaint->id }}, {{ $i }})">{{ $i }}</button>
                                            @endfor
                                        </div>
                                    @endif
                                @endif
                            @endif
                        </div>
                    @empty
                        <span class="text-body-secondary">{{ __('You have not raised any complaints.') }}</span>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
</div>
