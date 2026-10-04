<div>
    <h4 class="mb-1">{{ __('Gate terminal') }}</h4>
    <p class="text-body-secondary mb-4">{{ __('Every departure runs the full collection authority check. No fast path. No override.') }}</p>

    @if ($lastResult)
        <div class="alert {{ $lastResult === 'RELEASE' ? 'alert-success' : 'alert-danger' }} text-center py-5 mb-4" style="font-size: 3rem; font-weight: 800; letter-spacing: 2px;">
            {{ $lastResult }}
            @if ($lastReason)
                <div style="font-size: 1.1rem; font-weight: 500;">{{ str_replace('_', ' ', $lastReason) }}</div>
            @endif
        </div>
        <button type="button" class="btn btn-secondary mb-4" wire:click="resetLookup">{{ __('Next') }}</button>
    @endif

    @if (! $foundExeat)
        <div class="card">
            <div class="card-header">{{ __('Scan or enter verification code') }}</div>
            <div class="card-body">
                <div class="input-group">
                    <input type="text" class="form-control form-control-lg" wire:model="verificationCode" placeholder="{{ __('Verification code') }}">
                    <button type="button" class="btn btn-primary" wire:click="lookup">{{ __('Look up') }}</button>
                </div>
            </div>
        </div>
    @else
        <div class="card mb-4">
            <div class="card-header">{{ __('Exeat') }} {{ $foundExeat->exeat_number }}</div>
            <div class="card-body">
                <p class="mb-1"><strong>{{ $foundExeat->student->first_name }} {{ $foundExeat->student->last_name }}</strong></p>
                <p class="mb-1 text-body-secondary">{{ __('Status') }}: {{ ucfirst($foundExeat->status) }}</p>
                <p class="mb-0 text-body-secondary">{{ __('Returns by') }}: {{ $foundExeat->returns_by->format('Y-m-d H:i') }}</p>
            </div>
        </div>

        @if ($foundExeat->status === 'approved')
            <div class="card">
                <div class="card-header">{{ __('Who is collecting?') }}</div>
                <div class="card-body">
                    <input type="text" class="form-control mb-2" wire:model="claimName" placeholder="{{ __('Name of person at the gate') }}">
                    <input type="number" class="form-control mb-2" wire:model="claimGuardianId" placeholder="{{ __('Guardian ID if a listed guardian (optional)') }}">
                    <input type="text" class="form-control mb-2" wire:model="claimIdNo" placeholder="{{ __('ID number presented (optional)') }}">
                    <input type="text" class="form-control mb-2" wire:model="claimRelationship" placeholder="{{ __('Claimed relationship (optional)') }}">
                    <div class="form-check mb-3">
                        <input type="checkbox" class="form-check-input" wire:model="identityVerified" id="identityVerified">
                        <label class="form-check-label" for="identityVerified">{{ __('Photo ID verified in person') }}</label>
                    </div>
                    <button type="button" class="btn btn-primary btn-lg w-100" wire:click="checkDeparture">{{ __('Check authority & record departure') }}</button>
                </div>
            </div>
        @elseif ($foundExeat->status === 'departed' || $foundExeat->status === 'overdue')
            <div class="card">
                <div class="card-header">{{ __('Record return') }}</div>
                <div class="card-body">
                    <button type="button" class="btn btn-success btn-lg w-100" wire:click="recordReturn">{{ __('Record return now') }}</button>
                </div>
            </div>
        @endif

        @if ($recentAttempts->isNotEmpty())
            <div class="card mt-4">
                <div class="card-header">{{ __('Attempt history for this exeat') }}</div>
                <ul class="list-group list-group-flush">
                    @foreach ($recentAttempts as $attempt)
                        <li class="list-group-item d-flex justify-content-between">
                            <span>{{ $attempt->attempted_by_name }} ({{ $attempt->occurred_at->format('H:i') }})</span>
                            <span class="badge text-bg-{{ $attempt->outcome === 'released' ? 'success' : 'danger' }}">{{ ucfirst($attempt->outcome) }} {{ $attempt->refusal_reason ? '— '.str_replace('_', ' ', $attempt->refusal_reason) : '' }}</span>
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif
    @endif
</div>
