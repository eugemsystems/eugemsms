<div>
    <div class="mb-4">
        <h4 class="mb-0">{{ __('CBT tests') }}</h4>
        <p class="text-body-secondary small mb-0">{{ __('Hand-pick or draw by rule. Scheduling locks the questions; closing submits anything still in flight; results release only when every written answer is marked.') }}</p>
    </div>
    <div class="row g-4">
        <div class="col-xl-7">
            @forelse ($tests as $test)
                <div class="card mb-3" wire:key="t-{{ $test->id }}"><div class="card-body">
                    <div class="d-flex flex-wrap gap-2 align-items-center"><strong>{{ $test->title }}</strong><span class="text-body-secondary small">{{ $subjectNames[$test->subject_id] ?? '' }}</span><span class="badge text-bg-{{ ['draft' => 'secondary', 'scheduled' => 'info', 'open' => 'success', 'closed' => 'warning', 'results_released' => 'dark'][$test->status] ?? 'secondary' }} ms-auto">{{ __(ucfirst(str_replace('_', ' ', $test->status))) }}</span></div>
                    <div class="small text-body-secondary">{{ count($test->question_ids ?? []) }} {{ __('questions') }} · {{ $test->duration_minutes }} {{ __('min') }} · {{ $test->opens_at?->toDayDateTimeString() }} → {{ $test->closes_at?->toDayDateTimeString() }} · {{ $attemptCounts[$test->id] ?? 0 }} {{ __('attempts') }}@if ($test->browser_focus_monitoring) · {{ __('focus monitored (limit :n)', ['n' => $test->max_tab_switches ?? '—']) }} @endif</div>
                    <div class="mt-2 d-flex gap-1">
                        @if ($test->status === 'draft') <button type="button" class="btn btn-sm btn-outline-primary" wire:click="schedule({{ $test->id }})" wire:confirm="{{ __('Schedule this test? Its questions will be locked.') }}">{{ __('Schedule') }}</button> @endif
                        @if (in_array($test->status, ['scheduled', 'open'])) <button type="button" class="btn btn-sm btn-outline-warning" wire:click="close({{ $test->id }})" wire:confirm="{{ __('Close the test? Candidates still writing are submitted now.') }}">{{ __('Close') }}</button> @endif
                        @if ($test->status === 'closed') <button type="button" class="btn btn-sm btn-outline-success" wire:click="publish({{ $test->id }})" wire:confirm="{{ __('Release results?') }}">{{ __('Release results') }}</button> @endif
                    </div>
                </div></div>
            @empty
                <div class="text-body-secondary">{{ __('No tests yet.') }}</div>
            @endforelse
        </div>
        <div class="col-xl-5"><div class="card"><div class="card-header">{{ __('New test') }}</div><div class="card-body">
            <input type="text" class="form-control form-control-sm mb-2" wire:model="title" placeholder="{{ __('Title') }}">
            @error('title') <div class="text-danger small mb-2">{{ $message }}</div> @enderror
            <div class="row g-2 mb-2"><div class="col-7"><select class="form-select form-select-sm" wire:model.live="subjectId"><option value="">{{ __('Subject…') }}</option>@foreach ($subjects as $subject) <option value="{{ $subject->id }}">{{ $subject->name }}</option> @endforeach</select></div><div class="col-5"><select class="form-select form-select-sm" wire:model="termId">@foreach ($terms as $term) <option value="{{ $term->id }}">{{ $term->name }}</option> @endforeach</select></div></div>
            @if ($subjectId) <div class="small text-body-secondary mb-2">{{ __('In the bank:') }} {{ __('easy') }} {{ $availability['easy'] ?? 0 }} · {{ __('medium') }} {{ $availability['medium'] ?? 0 }} · {{ __('hard') }} {{ $availability['hard'] ?? 0 }}</div> @endif
            <select class="form-select form-select-sm mb-2" wire:model.live="method"><option value="manual">{{ __('Pick questions by hand') }}</option><option value="rule_based">{{ __('Draw by rule') }}</option></select>
            @if ($method === 'manual')
                <div class="border rounded p-2 mb-2" style="max-height: 220px; overflow:auto">@forelse ($bank as $question) <div class="form-check"><input class="form-check-input" type="checkbox" id="qb-{{ $question->id }}" value="{{ $question->id }}" wire:model="questionIds"><label class="form-check-label small" for="qb-{{ $question->id }}">[{{ $question->difficulty }}] {{ \Illuminate\Support\Str::limit($question->prompt, 60) }} ({{ $question->max_mark }})</label></div> @empty <span class="small text-body-secondary">{{ __('Choose a subject to see its questions.') }}</span> @endforelse</div>
            @else
                <div class="row g-2 mb-2"><div class="col-3"><input type="number" min="1" class="form-control form-control-sm" wire:model="count" placeholder="{{ __('Count') }}"></div><div class="col-3"><input type="number" class="form-control form-control-sm" wire:model="easyPercent" placeholder="{{ __('Easy %') }}"></div><div class="col-3"><input type="number" class="form-control form-control-sm" wire:model="mediumPercent" placeholder="{{ __('Medium %') }}"></div><div class="col-3"><input type="number" class="form-control form-control-sm" wire:model="hardPercent" placeholder="{{ __('Hard %') }}"></div></div>
                @error('easyPercent') <div class="text-danger small mb-2">{{ $message }}</div> @enderror
                <input type="text" class="form-control form-control-sm mb-2" wire:model="topics" placeholder="{{ __('Topics (comma separated, optional)') }}">
            @endif
            <div class="row g-2 mb-2"><div class="col-4"><input type="number" min="1" max="600" class="form-control form-control-sm" wire:model="duration" placeholder="{{ __('Minutes') }}"></div><div class="col-8"><select class="form-select form-select-sm" wire:model="assessmentTypeId"><option value="">{{ __('Not in the gradebook') }}</option>@foreach ($assessmentTypes as $type) <option value="{{ $type->id }}">{{ $type->name }}</option> @endforeach</select></div></div>
            <div class="row g-2 mb-2"><div class="col-6"><input type="datetime-local" class="form-control form-control-sm" wire:model="opensAt"></div><div class="col-6"><input type="datetime-local" class="form-control form-control-sm" wire:model="closesAt"></div></div>
            @error('opensAt') <div class="text-danger small mb-2">{{ $message }}</div> @enderror
            <div class="form-check"><input class="form-check-input" type="checkbox" id="rq" wire:model="randomiseQuestions"><label class="form-check-label small" for="rq">{{ __('Randomise question order') }}</label></div>
            <div class="form-check"><input class="form-check-input" type="checkbox" id="ro" wire:model="randomiseOptions"><label class="form-check-label small" for="ro">{{ __('Randomise option order') }}</label></div>
            <div class="form-check mb-2"><input class="form-check-input" type="checkbox" id="fm" wire:model.live="focusMonitoring"><label class="form-check-label small" for="fm">{{ __('Log tab switches (flags for review, never penalises)') }}</label></div>
            @if ($focusMonitoring) <input type="number" min="0" class="form-control form-control-sm mb-2" wire:model="maxTabSwitches" placeholder="{{ __('Flag after this many switches') }}"> @endif
            <button type="button" class="btn btn-primary btn-sm" wire:click="create">{{ __('Create draft') }}</button>
        </div></div></div>
    </div>
</div>
