<div>
    <h4 class="mb-1">{{ __('Counselling diary') }}</h4>
    <p class="text-body-secondary mb-4">{{ __('Your own sessions only. A risk indicator prompts you to escalate — the decision is yours and is recorded.') }}</p>

    @if ($staff === null)
        <div class="alert alert-warning">{{ __('No staff record is linked to your account — recording is disabled.') }}</div>
    @endif

    <div class="row g-4">
        <div class="col-md-7">
            @forelse ($sessions as $session)
                <div class="card mb-2" wire:key="session-{{ $session->id }}">
                    <div class="card-body py-2">
                        <div class="d-flex justify-content-between">
                            <span>{{ $session->student?->first_name }} {{ $session->student?->last_name }} — {{ $session->session_type }}</span>
                            @if ($session->risk_indicators_present)
                                <span class="badge text-bg-danger">{{ __('Risk indicators') }}</span>
                            @endif
                        </div>
                        <div class="small text-body-secondary">{{ $session->session_at->format('Y-m-d H:i') }} @if ($session->presenting_theme) · {{ $session->presenting_theme }} @endif</div>

                        @if ($session->risk_indicators_present && $session->escalated_to_case_id === null)
                            @if ($escalatingSessionId === $session->id)
                                <div class="mt-2">
                                    <select class="form-select form-select-sm mb-2" wire:model="escalationRiskLevel">
                                        <option value="low">{{ __('Low') }}</option>
                                        <option value="medium">{{ __('Medium') }}</option>
                                        <option value="high">{{ __('High') }}</option>
                                        <option value="critical">{{ __('Critical') }}</option>
                                    </select>
                                    <input type="text" class="form-control form-control-sm mb-2" wire:model="escalationSummary" placeholder="{{ __('Case summary') }}">
                                    <button type="button" class="btn btn-sm btn-danger" wire:click="escalate({{ $session->id }})">{{ __('Escalate to safeguarding') }}</button>
                                </div>
                            @else
                                <button type="button" class="btn btn-sm btn-outline-danger mt-2" wire:click="$set('escalatingSessionId', {{ $session->id }})">{{ __('Escalate to safeguarding') }}</button>
                            @endif
                        @elseif ($session->escalated_to_case_id !== null)
                            <span class="badge text-bg-secondary mt-2">{{ __('Escalated to a case') }}</span>
                        @endif
                    </div>
                </div>
            @empty
                <p class="text-body-secondary">{{ __('No sessions recorded.') }}</p>
            @endforelse
        </div>

        <div class="col-md-5">
            <div class="card">
                <div class="card-header">{{ __('Record session') }}</div>
                <div class="card-body">
                    <select class="form-select mb-2" wire:model="studentId">
                        <option value="">{{ __('Student') }}</option>
                        @foreach ($students as $student)
                            <option value="{{ $student->id }}">{{ $student->first_name }} {{ $student->last_name }}</option>
                        @endforeach
                    </select>
                    <select class="form-select mb-2" wire:model="sessionType">
                        <option value="individual">{{ __('Individual') }}</option>
                        <option value="group">{{ __('Group') }}</option>
                        <option value="family">{{ __('Family') }}</option>
                        <option value="crisis">{{ __('Crisis') }}</option>
                        <option value="follow_up">{{ __('Follow up') }}</option>
                    </select>
                    <select class="form-select mb-2" wire:model="referralSource">
                        <option value="">{{ __('Referral source (optional)') }}</option>
                        <option value="self">{{ __('Self') }}</option>
                        <option value="teacher">{{ __('Teacher') }}</option>
                        <option value="guardian">{{ __('Guardian') }}</option>
                        <option value="safeguarding">{{ __('Safeguarding') }}</option>
                        <option value="medical">{{ __('Medical') }}</option>
                    </select>
                    <input type="text" class="form-control mb-2" wire:model="presentingTheme" placeholder="{{ __('Presenting theme (optional)') }}">
                    <textarea class="form-control mb-2" wire:model="sessionNotes" placeholder="{{ __('Session notes — counsellor and lead only') }}"></textarea>
                    <input type="date" class="form-control mb-2" wire:model="nextSessionOn" placeholder="{{ __('Next session') }}">
                    <div class="form-check mb-1">
                        <input type="checkbox" class="form-check-input" id="riskIndicatorsPresent" wire:model="riskIndicatorsPresent">
                        <label class="form-check-label" for="riskIndicatorsPresent">{{ __('Risk indicators present') }}</label>
                    </div>
                    <div class="form-check mb-2">
                        <input type="checkbox" class="form-check-input" id="attended" wire:model="attended">
                        <label class="form-check-label" for="attended">{{ __('Attended') }}</label>
                    </div>
                    <button type="button" class="btn btn-primary btn-sm" wire:click="record" @disabled($staff === null)>{{ __('Record') }}</button>
                </div>
            </div>
        </div>
    </div>
</div>
