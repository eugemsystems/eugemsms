<div>
    <div class="mb-4"><h4 class="mb-0">{{ __('Interviews') }}</h4><p class="text-body-secondary small mb-0">{{ __('Book a panel, then record each criterion\'s score and a recommendation.') }}</p></div>
    <div class="row g-4">
        <div class="col-xl-8"><div class="card"><div class="table-responsive"><table class="table table-sm align-middle mb-0">
            <thead><tr><th>{{ __('Applicant') }}</th><th>{{ __('When') }}</th><th>{{ __('Outcome') }}</th><th></th></tr></thead>
            <tbody>@forelse ($interviews as $i) @php $a = $applications->get($i->application_id); @endphp
                <tr wire:key="i-{{ $i->id }}"><td>{{ $a?->last_name }}, {{ $a?->first_name }}</td><td>{{ $i->scheduled_at->format('d M H:i') }} <span class="small text-body-secondary">{{ $i->venue }}</span></td>
                    <td>@if ($i->completed_at) @if ($i->attended) {{ $i->total_score }} · <strong>{{ $i->recommendation }}</strong> @else {{ __('did not attend') }} @endif @else <span class="badge text-bg-light border">{{ __('waiting') }}</span> @endif</td>
                    <td class="text-end">@unless ($i->completed_at) <button type="button" class="btn btn-sm btn-outline-primary" wire:click="startRecording({{ $i->id }})">{{ __('Record') }}</button> @endunless</td></tr>
                @if ($recordingId === $i->id) <tr wire:key="r-{{ $i->id }}"><td colspan="4">
                    <textarea class="form-control form-control-sm mb-2" rows="3" wire:model="scoresText" placeholder="{{ __("One criterion per line: Criterion: score\nCommunication: 80") }}"></textarea>
                    <div class="row g-2 mb-2"><div class="col-4"><select class="form-select form-select-sm" wire:model="recommendation"><option value="accept">{{ __('Accept') }}</option><option value="waitlist">{{ __('Waitlist') }}</option><option value="decline">{{ __('Decline') }}</option></select></div><div class="col-8"><input type="text" class="form-control form-control-sm" wire:model="notes" placeholder="{{ __('Panel notes') }}"></div></div>
                    @error('scoresText') <div class="text-danger small mb-2">{{ $message }}</div> @enderror
                    <button type="button" class="btn btn-primary btn-sm" wire:click="record(true)">{{ __('Save outcome') }}</button> <button type="button" class="btn btn-outline-secondary btn-sm" wire:click="record(false)">{{ __('Did not attend') }}</button>
                </td></tr> @endif
            @empty <tr><td colspan="4" class="text-center text-body-secondary py-3">{{ __('No interviews.') }}</td></tr> @endforelse</tbody>
        </table></div></div></div>
        <div class="col-xl-4"><div class="card"><div class="card-header">{{ __('Schedule') }}</div><div class="card-body">
            <select class="form-select form-select-sm mb-2" wire:model="applicationId"><option value="">{{ __('Applicant…') }}</option>@foreach ($candidates as $c) <option value="{{ $c->id }}">{{ $c->last_name }}, {{ $c->first_name }}</option> @endforeach</select>
            @error('applicationId') <div class="text-danger small mb-2">{{ $message }}</div> @enderror
            <input type="datetime-local" class="form-control form-control-sm mb-2" wire:model="scheduledAt">
            <input type="text" class="form-control form-control-sm mb-2" wire:model="venue" placeholder="{{ __('Venue') }}">
            <select class="form-select form-select-sm mb-2" multiple size="5" wire:model="panel">@foreach ($staff as $member) <option value="{{ $member->id }}">{{ $member->name }}</option> @endforeach</select>
            <button type="button" class="btn btn-primary btn-sm" wire:click="schedule">{{ __('Schedule') }}</button>
        </div></div></div>
    </div>
</div>
