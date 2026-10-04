<div>
    <h4 class="mb-1">{{ __('Exeat') }} {{ $exeat->exeat_number }}</h4>
    <p class="text-body-secondary mb-4">
        {{ $exeat->student->first_name }} {{ $exeat->student->last_name }} — {{ $exeat->exeatType->name }}
        — <span class="badge text-bg-secondary">{{ ucfirst($exeat->status) }}</span>
    </p>

    <div class="row g-4">
        <div class="col-md-7">
            <div class="card mb-4">
                <div class="card-header">{{ __('Request') }}</div>
                <div class="card-body">
                    <p class="mb-1"><strong>{{ __('Reason') }}:</strong> {{ $exeat->reason }}</p>
                    <p class="mb-1"><strong>{{ __('Departs') }}:</strong> {{ $exeat->departs_at->format('Y-m-d H:i') }}</p>
                    <p class="mb-1"><strong>{{ __('Returns by') }}:</strong> {{ $exeat->returns_by->format('Y-m-d H:i') }}</p>
                    <p class="mb-1"><strong>{{ __('Destination') }}:</strong> {{ $exeat->destination_address }}, {{ $exeat->destination_province }}</p>
                    <p class="mb-1"><strong>{{ __('Collecting') }}:</strong> {{ $exeat->collectingGuardian?->displayName() ?? $exeat->collecting_person_name ?? '—' }}</p>
                    @if ($exeat->verification_code)
                        <p class="mb-0"><strong>{{ __('Verification code') }}:</strong> <code>{{ $exeat->verification_code }}</code></p>
                    @endif
                </div>
            </div>

            <div class="card">
                <div class="card-header">{{ __('Movement') }}</div>
                <div class="card-body">
                    <p class="mb-1"><strong>{{ __('Departed') }}:</strong> {{ $exeat->actual_departure_at?->format('Y-m-d H:i') ?? '—' }} @if ($exeat->departure_verified_by) ({{ $exeat->departure_verified_by }}) @endif</p>
                    <p class="mb-0"><strong>{{ __('Returned') }}:</strong> {{ $exeat->actual_return_at?->format('Y-m-d H:i') ?? '—' }} @if ($exeat->late_return_minutes) <span class="text-danger">({{ $exeat->late_return_minutes }}m late)</span> @endif</p>
                </div>
            </div>

            @if ($attempts->isNotEmpty())
                <div class="card mt-4">
                    <div class="card-header">{{ __('Collection attempts') }}</div>
                    <div class="table-responsive">
                        <table class="table table-sm mb-0">
                            <thead><tr><th>{{ __('Time') }}</th><th>{{ __('Claimed by') }}</th><th>{{ __('Outcome') }}</th></tr></thead>
                            <tbody>
                                @foreach ($attempts as $attempt)
                                    <tr><td>{{ $attempt->occurred_at->format('H:i') }}</td><td>{{ $attempt->attempted_by_name }}</td><td><span class="badge text-bg-{{ $attempt->outcome === 'released' ? 'success' : 'danger' }}">{{ ucfirst($attempt->outcome) }}</span></td></tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @endif
        </div>

        <div class="col-md-5">
            @if ($exeat->status === 'pending')
                <div class="card">
                    <div class="card-header">{{ __('Decision') }}</div>
                    <div class="card-body">
                        <button type="button" class="btn btn-success btn-sm mb-3" wire:click="approve">{{ __('Approve') }}</button>
                        <input type="text" class="form-control mb-2" wire:model="rejectionReason" placeholder="{{ __('Rejection reason') }}">
                        <button type="button" class="btn btn-danger btn-sm" wire:click="reject">{{ __('Reject') }}</button>
                    </div>
                </div>
            @endif
        </div>
    </div>
</div>
