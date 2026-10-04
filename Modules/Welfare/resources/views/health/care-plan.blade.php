<div>
    <h4 class="mb-1">{{ __('Emergency care plans') }} — {{ $student->first_name }} {{ $student->last_name }}</h4>
    <p class="text-body-secondary mb-4">{{ __('Tier 2 — plain language, printable, no diagnosis beyond what is needed to act.') }}</p>

    <div class="row g-4">
        <div class="col-md-7">
            @forelse ($plans as $plan)
                <div class="card mb-3" wire:key="plan-{{ $plan->id }}">
                    <div class="card-header d-flex justify-content-between">
                        <span>{{ $plan->title }}</span>
                        <span class="badge text-bg-{{ $plan->approved_at ? 'success' : 'secondary' }}">{{ $plan->approved_at ? __('Approved') : __('Pending approval') }}</span>
                    </div>
                    <div class="card-body small">
                        <div><strong>{{ __('Trigger signs') }}:</strong> {{ $plan->trigger_signs }}</div>
                        <div><strong>{{ __('Immediate actions') }}:</strong> {{ $plan->immediate_actions }}</div>
                        @if ($plan->do_not_do)
                            <div><strong>{{ __('Do not') }}:</strong> {{ $plan->do_not_do }}</div>
                        @endif
                        @if ($plan->medication_name)
                            <div><strong>{{ __('Medication') }}:</strong> {{ $plan->medication_name }} ({{ $plan->medication_location }})</div>
                        @endif
                        <div><strong>{{ __('Who to call') }}:</strong> {{ $plan->who_to_call }}</div>
                        @unless ($plan->approved_at)
                            <button type="button" class="btn btn-sm btn-outline-success mt-2" wire:click="approve({{ $plan->id }})">{{ __('Approve') }}</button>
                        @endunless
                    </div>
                </div>
            @empty
                <p class="text-body-secondary">{{ __('No care plans yet.') }}</p>
            @endforelse
        </div>

        <div class="col-md-5">
            <div class="card">
                <div class="card-header">{{ __('New care plan') }}</div>
                <div class="card-body">
                    <select class="form-select mb-2" wire:model="conditionId">
                        <option value="">{{ __('Related condition (optional)') }}</option>
                        @foreach ($conditions as $condition)
                            <option value="{{ $condition->id }}">{{ $condition->public_summary ?? __('Condition #:id', ['id' => $condition->id]) }}</option>
                        @endforeach
                    </select>
                    <input type="text" class="form-control mb-2" wire:model="title" placeholder="{{ __('Title — e.g. \'Anaphylaxis response\'') }}">
                    <textarea class="form-control mb-2" wire:model="triggerSigns" placeholder="{{ __('Trigger signs — what to look for') }}"></textarea>
                    <textarea class="form-control mb-2" wire:model="immediateActions" placeholder="{{ __('Immediate actions — what to do, in order') }}"></textarea>
                    <input type="text" class="form-control mb-2" wire:model="medicationName" placeholder="{{ __('Medication name (optional)') }}">
                    <input type="text" class="form-control mb-2" wire:model="medicationLocation" placeholder="{{ __('Medication location (optional)') }}">
                    <textarea class="form-control mb-2" wire:model="doNotDo" placeholder="{{ __('Do not do (optional)') }}"></textarea>
                    <input type="text" class="form-control mb-2" wire:model="whoToCall" placeholder="{{ __('Who to call') }}">
                    <input type="date" class="form-control mb-2" wire:model="reviewDueOn">
                    <button type="button" class="btn btn-primary btn-sm" wire:click="create">{{ __('Create') }}</button>
                </div>
            </div>
        </div>
    </div>
</div>
