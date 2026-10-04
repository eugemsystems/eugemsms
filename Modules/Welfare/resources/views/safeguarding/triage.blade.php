<div>
    <h4 class="mb-1">{{ __('Safeguarding triage') }}</h4>
    <p class="text-body-secondary mb-4">{{ __('Lead/deputy only. Every concern is triaged with a recorded rationale, including no_further_action.') }}</p>

    @forelse ($concerns as $concern)
        <div class="card mb-3 {{ $concern->immediate_risk ? 'border-danger' : '' }}" wire:key="concern-{{ $concern->id }}">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <strong>{{ str_replace('_', ' ', $concern->concern_category) }}</strong>
                    @if ($concern->immediate_risk)
                        <span class="badge text-bg-danger">{{ __('Immediate risk') }}</span>
                    @endif
                </div>
                <div class="small text-body-secondary mb-2">{{ __('Source') }}: {{ $concern->report_source }} · {{ $concern->reported_at->diffForHumans() }}</div>
                <p>{{ $concern->description }}</p>
                @if ($concern->initial_action_taken)
                    <p class="small"><strong>{{ __('Initial action') }}:</strong> {{ $concern->initial_action_taken }}</p>
                @endif

                @if ($triagingConcernId === $concern->id)
                    <div class="row g-2 mb-2">
                        <div class="col-4">
                            <select class="form-select form-select-sm" wire:model="triageStatus">
                                <option value="escalated">{{ __('Escalated') }}</option>
                                <option value="monitoring">{{ __('Monitoring') }}</option>
                                <option value="no_further_action">{{ __('No further action') }}</option>
                            </select>
                        </div>
                        <div class="col-6"><input type="text" class="form-control form-control-sm" wire:model="rationale" placeholder="{{ __('Rationale — mandatory') }}"></div>
                        <div class="col-2"><button type="button" class="btn btn-sm btn-success w-100" wire:click="triage({{ $concern->id }})">{{ __('Save') }}</button></div>
                    </div>
                @else
                    <button type="button" class="btn btn-sm btn-outline-primary" wire:click="$set('triagingConcernId', {{ $concern->id }})">{{ __('Triage') }}</button>
                @endif

                @if ($concern->student_id)
                    @if ($escalatingConcernId === $concern->id)
                        <div class="border rounded p-2 mt-2">
                            <select class="form-select form-select-sm mb-2" wire:model="category">
                                <option value="neglect">{{ __('Neglect') }}</option>
                                <option value="physical">{{ __('Physical') }}</option>
                                <option value="emotional">{{ __('Emotional') }}</option>
                                <option value="sexual">{{ __('Sexual') }}</option>
                                <option value="peer_on_peer">{{ __('Peer on peer') }}</option>
                                <option value="self_harm">{{ __('Self harm') }}</option>
                                <option value="bullying">{{ __('Bullying') }}</option>
                                <option value="online">{{ __('Online') }}</option>
                                <option value="exploitation">{{ __('Exploitation') }}</option>
                                <option value="home_circumstances">{{ __('Home circumstances') }}</option>
                                <option value="other">{{ __('Other') }}</option>
                            </select>
                            <select class="form-select form-select-sm mb-2" wire:model="riskLevel">
                                <option value="low">{{ __('Low') }}</option>
                                <option value="medium">{{ __('Medium') }}</option>
                                <option value="high">{{ __('High') }}</option>
                                <option value="critical">{{ __('Critical') }}</option>
                            </select>
                            <textarea class="form-control form-control-sm mb-2" wire:model="summary" placeholder="{{ __('Case summary') }}"></textarea>
                            <div class="form-check mb-1">
                                <input type="checkbox" class="form-check-input" id="gi-{{ $concern->id }}" wire:model="guardiansInformed">
                                <label class="form-check-label small" for="gi-{{ $concern->id }}">{{ __('Guardians informed') }}</label>
                            </div>
                            @if ($guardiansInformed === false)
                                <input type="text" class="form-control form-control-sm mb-2" wire:model="guardiansNotInformedReason" placeholder="{{ __('Reason guardians were not informed — required') }}">
                            @endif
                            <button type="button" class="btn btn-sm btn-danger" wire:click="escalate({{ $concern->id }})">{{ __('Open case') }}</button>
                        </div>
                    @else
                        <button type="button" class="btn btn-sm btn-outline-danger mt-2" wire:click="$set('escalatingConcernId', {{ $concern->id }})">{{ __('Open safeguarding case') }}</button>
                    @endif
                @endif
            </div>
        </div>
    @empty
        <p class="text-body-secondary">{{ __('Nothing awaiting triage.') }}</p>
    @endforelse
</div>
