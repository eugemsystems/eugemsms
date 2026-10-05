<div>
    <div class="d-flex align-items-center gap-2 mb-4">
        <a href="{{ route('comms.complaints.queue', $school) }}" class="btn btn-icon btn-outline-secondary btn-sm" wire:navigate>
            <i class="ri ri-arrow-left-line"></i>
        </a>
        <div>
            <h4 class="mb-0">{{ __('Exit interviews') }}</h4>
            <p class="text-body-secondary small mb-0">{{ __('Offered to leaving families, never required. A decline is recorded too — the decline rate is useful data.') }}</p>
        </div>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-3"><div class="card"><div class="card-body py-2"><div class="small text-body-secondary">{{ __('Pending') }}</div><div class="h5 mb-0">{{ $pending }}</div></div></div></div>
        <div class="col-3"><div class="card"><div class="card-body py-2"><div class="small text-body-secondary">{{ __('Completed') }}</div><div class="h5 mb-0">{{ $completed }}</div></div></div></div>
        <div class="col-3"><div class="card"><div class="card-body py-2"><div class="small text-body-secondary">{{ __('Declined') }}</div><div class="h5 mb-0">{{ $declined }}</div></div></div></div>
        <div class="col-3"><div class="card"><div class="card-body py-2"><div class="small text-body-secondary">{{ __('Decline rate') }}</div><div class="h5 mb-0">{{ $declineRate !== null ? $declineRate.'%' : '—' }}</div></div></div></div>
    </div>

    <div class="row g-4">
        <div class="col-lg-8">
            <div class="card">
                <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        <thead><tr><th>{{ __('Learner') }}</th><th>{{ __('Offered') }}</th><th>{{ __('Outcome') }}</th><th></th></tr></thead>
                        <tbody>
                            @forelse ($interviews as $interview)
                                <tr wire:key="ei-{{ $interview->id }}">
                                    <td>{{ $students->get($interview->student_id)?->first_name }} {{ $students->get($interview->student_id)?->last_name }}</td>
                                    <td class="small">{{ $interview->requested_at->toDateString() }}</td>
                                    <td class="small">
                                        @if ($interview->completed_at === null) <span class="badge bg-label-warning">{{ __('pending') }}</span>
                                        @elseif ($interview->wasDeclined()) <span class="badge bg-label-secondary">{{ __('declined') }}</span>
                                        @else <span class="badge bg-label-success">{{ __('completed') }}</span> {{ $reasons[$interview->primary_reason] ?? $interview->primary_reason }} @endif
                                    </td>
                                    <td class="text-end text-nowrap">
                                        @if ($interview->completed_at === null)
                                            <button type="button" class="btn btn-xs btn-outline-primary" wire:click="$set('completingId', {{ $interview->id }})">{{ __('Record') }}</button>
                                            <button type="button" class="btn btn-xs btn-outline-secondary" wire:click="decline({{ $interview->id }})" wire:confirm="{{ __('Record that the family declined?') }}">{{ __('Declined') }}</button>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="4" class="text-center text-body-secondary py-3">{{ __('No exit interviews yet.') }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <div class="col-lg-4">
            <div class="card mb-4">
                <div class="card-header">{{ __('Offer an exit interview') }}</div>
                <div class="card-body">
                    <input type="text" class="form-control mb-2" wire:model="admissionNumber" placeholder="{{ __('Learner admission no.') }}">
                    @error('admissionNumber') <div class="text-danger small mb-2">{{ $message }}</div> @enderror
                    <button type="button" class="btn btn-primary btn-sm" wire:click="request">{{ __('Offer') }}</button>
                </div>
            </div>

            @if ($completingId)
                <div class="card">
                    <div class="card-header">{{ __('Record the interview') }}</div>
                    <div class="card-body">
                        <select class="form-select mb-2" wire:model="primaryReason">
                            <option value="">{{ __('Main reason for leaving…') }}</option>
                            @foreach ($reasons as $value => $label) <option value="{{ $value }}">{{ $label }}</option> @endforeach
                        </select>
                        @error('primaryReason') <div class="text-danger small mb-2">{{ $message }}</div> @enderror
                        <select class="form-select mb-2" wire:model="responseSource">
                            <option value="call">{{ __('By phone call') }}</option>
                            <option value="survey">{{ __('By survey') }}</option>
                        </select>
                        <textarea class="form-control mb-2" rows="3" wire:model="detail" placeholder="{{ __('Detail (optional)') }}"></textarea>
                        <select class="form-select mb-2" wire:model="wouldRecommend">
                            <option value="">{{ __('Would recommend the school? — not stated') }}</option>
                            <option value="yes">{{ __('Yes') }}</option>
                            <option value="no">{{ __('No') }}</option>
                        </select>
                        <button type="button" class="btn btn-primary btn-sm" wire:click="complete">{{ __('Save') }}</button>
                    </div>
                </div>
            @endif
        </div>
    </div>
</div>
