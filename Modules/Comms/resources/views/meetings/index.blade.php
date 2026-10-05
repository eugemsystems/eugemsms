<div>
    <div class="d-flex justify-content-between align-items-start mb-3">
        <div>
            <h4 class="mb-1">{{ __('Meeting schedule') }}</h4>
            <p class="text-body-secondary small mb-0">{{ __('Learner-facing meetings always start with a waiting room. Host links and passcodes are shown only to the meeting’s own host.') }}</p>
        </div>
        <div class="d-flex gap-2">
            <a href="{{ route('comms.meetings.attendance', $school) }}" class="btn btn-outline-secondary btn-sm" wire:navigate>{{ __('Attendance review') }}</a>
            <a href="{{ route('comms.meetings.recordings', $school) }}" class="btn btn-outline-secondary btn-sm" wire:navigate>{{ __('Recordings') }}</a>
            <a href="{{ route('comms.meetings.consultations', $school) }}" class="btn btn-outline-secondary btn-sm" wire:navigate>{{ __('Consultations') }}</a>
            @if ($canManage) <a href="{{ route('comms.meetings.providers', $school) }}" class="btn btn-outline-secondary btn-sm" wire:navigate>{{ __('Providers') }}</a> @endif
        </div>
    </div>

    @if ($revealed)
        <div class="alert alert-warning small">
            <strong>{{ __('Host credentials') }}</strong> — {{ __('visible only to you as the host; do not share.') }}
            <div>{{ __('Host link') }}: {{ $revealed['host_url'] ?? '—' }}</div>
            <div>{{ __('Passcode') }}: {{ $revealed['passcode'] ?? '—' }}</div>
            <button type="button" class="btn btn-sm btn-outline-secondary mt-1" wire:click="hideHostCredentials">{{ __('Hide') }}</button>
        </div>
    @endif

    <select class="form-select form-select-sm mb-3 w-auto" wire:model.live="statusFilter">
        <option value="scheduled">{{ __('Scheduled') }}</option>
        <option value="in_progress">{{ __('In progress') }}</option>
        <option value="completed">{{ __('Completed') }}</option>
        <option value="cancelled">{{ __('Cancelled') }}</option>
        <option value="">{{ __('All') }}</option>
    </select>

    <div class="row g-4">
        <div class="{{ $canManage ? 'col-lg-8' : 'col-12' }}">
            <div class="card">
                <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        <thead><tr><th>{{ __('When') }}</th><th>{{ __('Type') }}</th><th>{{ __('Host') }}</th><th>{{ __('Waiting room') }}</th><th>{{ __('Status') }}</th><th></th></tr></thead>
                        <tbody>
                            @forelse ($meetings as $meeting)
                                <tr wire:key="mtg-{{ $meeting->id }}">
                                    <td class="small">{{ $meeting->starts_at->format('D j M, H:i') }} ({{ $meeting->duration_minutes }} {{ __('min') }})</td>
                                    <td>{{ str_replace('_', ' ', $meeting->meeting_type) }}
                                        @if ($meeting->join_url) <a href="{{ $meeting->join_url }}" target="_blank" rel="noopener noreferrer" class="small">{{ __('join') }}</a> @endif</td>
                                    <td class="small">{{ $hosts->get($meeting->host_staff_id)?->fullName() ?? '—' }}</td>
                                    <td>{{ $meeting->waiting_room_enabled ? __('on') : __('off') }}</td>
                                    <td><span class="badge {{ $meeting->status === 'cancelled' ? 'bg-label-danger' : ($meeting->status === 'completed' ? 'bg-label-success' : 'bg-label-secondary') }}">{{ $meeting->status }}</span></td>
                                    <td class="text-end text-nowrap">
                                        @if ($meeting->host_staff_id) <button type="button" class="btn btn-xs btn-outline-secondary" wire:click="revealHostCredentials({{ $meeting->id }})">{{ __('Host link') }}</button> @endif
                                        @if ($meeting->meeting_type === 'online_lesson' && $meeting->waiting_room_enabled && $meeting->status === 'scheduled')
                                            <button type="button" class="btn btn-xs btn-outline-warning" wire:click="disableWaitingRoom({{ $meeting->id }})" wire:confirm="{{ __('Turn off the waiting room for a learner meeting? This is logged.') }}">{{ __('Waiting room off') }}</button>
                                        @endif
                                        @if ($canManage && $meeting->status === 'scheduled')
                                            <button type="button" class="btn btn-xs btn-outline-danger" wire:click="cancel({{ $meeting->id }})" wire:confirm="{{ __('Cancel this meeting? Booked participants are notified.') }}">{{ __('Cancel') }}</button>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="6" class="text-center text-body-secondary py-3">{{ __('No meetings.') }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        @if ($canManage)
            <div class="col-lg-4">
                <div class="card">
                    <div class="card-header">{{ __('Schedule a meeting') }}</div>
                    <div class="card-body">
                        <select class="form-select mb-2" wire:model.live="meetingType">
                            <option value="staff_meeting">{{ __('Staff meeting') }}</option>
                            <option value="board_meeting">{{ __('Board meeting') }}</option>
                            <option value="webinar">{{ __('Webinar') }}</option>
                            <option value="consultation">{{ __('Consultation') }}</option>
                            <option value="online_lesson">{{ __('Online lesson') }}</option>
                        </select>
                        <select class="form-select mb-2" wire:model="providerId">
                            <option value="">{{ __('Provider…') }}</option>
                            @foreach ($providers as $provider) <option value="{{ $provider->id }}">{{ str_replace('_', ' ', $provider->provider) }}</option> @endforeach
                        </select>
                        @error('providerId') <div class="text-danger small mb-2">{{ $message }}</div> @enderror
                        <input type="text" class="form-control mb-2" wire:model="topic" placeholder="{{ __('Topic') }}">
                        @error('topic') <div class="text-danger small mb-2">{{ $message }}</div> @enderror
                        <input type="datetime-local" class="form-control mb-2" wire:model="startsAt">
                        @error('startsAt') <div class="text-danger small mb-2">{{ $message }}</div> @enderror
                        <input type="number" class="form-control mb-2" wire:model="durationMinutes" min="5" title="{{ __('Duration (minutes)') }}">
                        <select class="form-select mb-2" wire:model="hostStaffId">
                            <option value="">{{ __('Host (optional)…') }}</option>
                            @foreach ($staff as $member) <option value="{{ $member->id }}">{{ $member->fullName() }}</option> @endforeach
                        </select>
                        @if ($meetingType === 'online_lesson')
                            <select class="form-select mb-2" wire:model="timetableSlotId">
                                <option value="">{{ __('Timetable slot…') }}</option>
                                @foreach ($slots as $slot) <option value="{{ $slot->id }}">{{ $slot->subject?->name ?? __('Slot') }} — {{ __('day :d, period :p', ['d' => $slot->cycle_day, 'p' => $slot->period_number]) }}</option> @endforeach
                            </select>
                            @error('timetableSlotId') <div class="text-danger small mb-2">{{ $message }}</div> @enderror
                            <div class="small text-body-secondary mb-2">{{ __('The waiting room is on for learner meetings and cannot be turned off here.') }}</div>
                        @else
                            <div class="form-check mb-1"><input class="form-check-input" type="checkbox" id="mtg-wr" wire:model="waitingRoomEnabled"><label class="form-check-label small" for="mtg-wr">{{ __('Waiting room') }}</label></div>
                        @endif
                        <div class="form-check mb-2"><input class="form-check-input" type="checkbox" id="mtg-rec" wire:model="recordingEnabled"><label class="form-check-label small" for="mtg-rec">{{ __('Record') }}</label></div>
                        <button type="button" class="btn btn-primary btn-sm" wire:click="schedule">{{ __('Schedule') }}</button>
                    </div>
                </div>
            </div>
        @endif
    </div>
</div>
