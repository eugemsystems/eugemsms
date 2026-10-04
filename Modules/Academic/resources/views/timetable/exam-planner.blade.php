<div>
    <h4 class="mb-1">{{ __('Exam slot planner') }}</h4>
    <p class="text-body-secondary mb-4">{{ __('Reserve a public examination period and view the disruption report.') }}</p>

    <div class="row g-4">
        <div class="col-md-7">
            @forelse ($plans as $plan)
                <div class="card mb-3" wire:key="plan-{{ $plan->id }}">
                    <div class="card-header d-flex justify-content-between">
                        <span>{{ $plan->name }}</span>
                        <span class="badge text-bg-secondary">{{ ucfirst($plan->status) }}</span>
                    </div>
                    <div class="card-body">
                        <p class="mb-2 text-body-secondary">{{ $plan->starts_on->toFormattedDateString() }} – {{ $plan->ends_on->toFormattedDateString() }} ({{ strtoupper($plan->exam_body) }})</p>
                        @if (is_array($plan->disruption_report) && count($plan->disruption_report) > 0)
                            <strong>{{ __('Disruption report:') }}</strong>
                            <ul class="mb-0">
                                @foreach ($plan->disruption_report as $row)
                                    <li>{{ $levelNames[$row['grade_level_id']] ?? "#{$row['grade_level_id']}" }} — {{ $subjectNames[$row['subject_id']] ?? "#{$row['subject_id']}" }}: {{ $row['periods_lost'] }} {{ __('period(s) lost') }}</li>
                                @endforeach
                            </ul>
                        @else
                            <span class="text-body-secondary">{{ __('No disruption report — no published timetable for this term yet.') }}</span>
                        @endif
                    </div>
                </div>
            @empty
                <div class="card"><div class="card-body text-center text-body-secondary">{{ __('No exam slot plans yet.') }}</div></div>
            @endforelse
        </div>

        <div class="col-md-5">
            <div class="card">
                <div class="card-header">{{ __('New exam slot plan') }}</div>
                <div class="card-body">
                    <form wire:submit="create">
                        <div class="row g-3">
                            <div class="col-12">
                                <div class="form-floating form-floating-outline">
                                    <input type="text" class="form-control @error('name') is-invalid @enderror" wire:model="name" placeholder=" ">
                                    <label>{{ __('Name') }}</label>
                                    @error('name') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-floating form-floating-outline">
                                    <select class="form-select" wire:model="examBody">
                                        <option value="zimsec">{{ __('ZIMSEC') }}</option>
                                        <option value="cambridge">{{ __('Cambridge') }}</option>
                                        <option value="internal">{{ __('Internal') }}</option>
                                    </select>
                                    <label>{{ __('Exam body') }}</label>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-floating form-floating-outline">
                                    <input type="date" class="form-control @error('startsOn') is-invalid @enderror" wire:model="startsOn">
                                    <label>{{ __('Starts on') }}</label>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-floating form-floating-outline">
                                    <input type="date" class="form-control @error('endsOn') is-invalid @enderror" wire:model="endsOn">
                                    <label>{{ __('Ends on') }}</label>
                                </div>
                            </div>
                            <div class="col-12">
                                <label class="form-label">{{ __('Affected levels') }}</label>
                                <div class="row">
                                    @foreach ($levels as $level)
                                        <div class="col-md-6 form-check">
                                            <input type="checkbox" class="form-check-input" wire:model="affectedLevels" value="{{ $level->id }}" id="level-{{ $level->id }}">
                                            <label class="form-check-label" for="level-{{ $level->id }}">{{ $level->name }}</label>
                                        </div>
                                    @endforeach
                                </div>
                                @error('affectedLevels') <div class="text-danger small">{{ $message }}</div> @enderror
                            </div>
                        </div>
                        <button type="submit" class="btn btn-primary mt-3" wire:loading.attr="disabled">{{ __('Create plan') }}</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
