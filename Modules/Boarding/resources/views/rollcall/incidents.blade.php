<div>
    <h4 class="mb-1">{{ __('Incident console') }}</h4>
    <p class="text-body-secondary mb-4">
        <strong>{{ __('Acknowledgement is not resolution.') }}</strong>
        {{ __('The ladder advances on elapsed time regardless of who has acknowledged what. Someone must record what they found.') }}
    </p>

    @forelse ($incidents as $incident)
        @php $steps = $stepsByProfile->get($incident->escalation_profile_id, collect()); @endphp
        <div class="card mb-3 border-danger">
            <div class="card-header d-flex justify-content-between align-items-center bg-danger-subtle">
                <span>
                    <strong>{{ $incident->student->first_name }} {{ $incident->student->last_name }}</strong>
                    — {{ __('missing since') }} {{ $incident->first_missed_at->format('H:i') }}
                    — {{ __('current step') }}: {{ $incident->current_step }}
                </span>
                <span>
                    <span class="badge text-bg-{{ $incident->status === 'escalating' ? 'danger' : 'warning' }}">{{ ucfirst($incident->status) }}</span>
                    <a href="{{ route('boarding.rollcall.incident', [$school, $incident]) }}" class="btn btn-sm btn-outline-secondary ms-2" wire:navigate>{{ __('Full timeline') }}</a>
                    <button type="button" class="btn btn-sm btn-outline-secondary" wire:click="advanceNow({{ $incident->id }})">{{ __('Check ladder') }}</button>
                </span>
            </div>
            <div class="card-body">
                @php $currentStepDef = $steps->firstWhere('step_number', $incident->current_step); @endphp
                <p class="mb-2 small text-body-secondary">
                    {{ __('Step :n notifies', ['n' => $incident->current_step]) }}
                    @if ($currentStepDef?->requires_action_record)
                        <span class="text-danger">— {{ __('a free-text action record is mandatory at this step.') }}</span>
                    @endif
                </p>

                <div class="d-flex gap-2 flex-wrap">
                    <button type="button" class="btn btn-sm btn-outline-primary" wire:click="acknowledge({{ $incident->id }}, {{ $incident->current_step }})">{{ __('Acknowledge step :n', ['n' => $incident->current_step]) }}</button>
                </div>

                <div class="input-group mt-2">
                    <input type="text" class="form-control" wire:model="actionTaken.{{ $incident->id }}" placeholder="{{ __('What did you actually check? (mandatory to satisfy this step)') }}">
                    <button type="button" class="btn btn-danger" wire:click="recordAction({{ $incident->id }}, {{ $incident->current_step }})">{{ __('Record action') }}</button>
                </div>

                @if ($incident->actions->isNotEmpty())
                    <ul class="list-unstyled small text-body-secondary mt-2 mb-0">
                        @foreach ($incident->actions->sortByDesc('occurred_at')->take(3) as $action)
                            <li>{{ $action->occurred_at->format('H:i') }} — {{ str_replace('_', ' ', $action->action_type) }} @if ($action->action_taken) : "{{ $action->action_taken }}" @endif</li>
                        @endforeach
                    </ul>
                @endif
            </div>
        </div>
    @empty
        <div class="card"><div class="card-body text-body-secondary">{{ __('No open incidents.') }}</div></div>
    @endforelse
</div>
