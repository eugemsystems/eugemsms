<div>
    <h4 class="mb-1">{{ __('Report a safeguarding concern') }}</h4>
    <p class="text-body-secondary mb-4">{{ __('The cost of a report that turns out to be nothing is far lower than the cost of one never made. No approval is needed to submit.') }}</p>

    <div class="row g-4">
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">{{ __('Report') }}</div>
                <div class="card-body">
                    <select class="form-select mb-2" wire:model="studentId">
                        <option value="">{{ __('Who it concerns (optional)') }}</option>
                        @foreach ($students as $student)
                            <option value="{{ $student->id }}">{{ $student->first_name }} {{ $student->last_name }}</option>
                        @endforeach
                    </select>
                    <select class="form-select mb-2" wire:model="concernCategory">
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
                    <textarea class="form-control mb-2" wire:model="description" placeholder="{{ __('What happened') }}"></textarea>
                    <input type="text" class="form-control mb-2" wire:model="initialActionTaken" placeholder="{{ __('Initial action taken (optional)') }}">

                    <div class="form-check mb-1">
                        <input type="checkbox" class="form-check-input" id="immediateRisk" wire:model="immediateRisk">
                        <label class="form-check-label text-danger" for="immediateRisk">{{ __('Immediate risk — alerts the lead and deputy now, bypassing quiet hours') }}</label>
                    </div>
                    <div class="form-check mb-3">
                        <input type="checkbox" class="form-check-input" id="reportAnonymously" wire:model="reportAnonymously">
                        <label class="form-check-label" for="reportAnonymously">{{ __('Report anonymously — no identity is stored') }}</label>
                    </div>

                    <button type="button" class="btn btn-primary" wire:click="submit">{{ __('Submit') }}</button>

                    @if ($generatedToken)
                        <div class="alert alert-warning mt-3">
                            {{ __('Your token (shown once — this is the only way to follow up):') }}
                            <code>{{ $generatedToken }}</code>
                        </div>
                    @endif
                </div>
            </div>
        </div>

        <div class="col-md-6">
            <div class="card">
                <div class="card-header">{{ __('Follow up on an anonymous report') }}</div>
                <div class="card-body">
                    <input type="text" class="form-control mb-2" wire:model="followUpToken" placeholder="{{ __('Your token') }}">
                    <button type="button" class="btn btn-outline-secondary btn-sm" wire:click="followUp">{{ __('Check status') }}</button>
                    @if ($followUpStatus)
                        <p class="mt-2">{{ $followUpStatus }}</p>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
