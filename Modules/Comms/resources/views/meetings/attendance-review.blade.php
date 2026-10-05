<div>
    <div class="d-flex align-items-center gap-2 mb-4">
        <a href="{{ route('comms.meetings.index', $school) }}" class="btn btn-icon btn-outline-secondary btn-sm" wire:navigate>
            <i class="ri ri-arrow-left-line"></i>
        </a>
        <div>
            <h4 class="mb-0">{{ __('Online lesson attendance') }} ⭐</h4>
            <p class="text-body-secondary small mb-0">{{ __('Join and leave times from the video call are a suggestion only. Nothing is written to the register until you confirm, and you choose each learner’s status.') }}</p>
        </div>
    </div>

    <select class="form-select form-select-sm mb-3 w-auto" wire:model.live="meetingId">
        <option value="">{{ __('Choose an online lesson…') }}</option>
        @foreach ($meetings as $meeting) <option value="{{ $meeting->id }}">{{ $meeting->starts_at->format('D j M, H:i') }} ({{ $meeting->duration_minutes }} {{ __('min') }}, {{ $meeting->status }})</option> @endforeach
    </select>
    @error('meetingId') <div class="alert alert-warning small">{{ $message }}</div> @enderror

    @if ($sessionId)
        @error('decisions') <div class="alert alert-danger small">{{ $message }}</div> @enderror

        <div class="card">
            <div class="table-responsive">
                <table class="table table-sm mb-0">
                    <thead><tr><th>{{ __('Participant') }}</th><th>{{ __('Learner') }}</th><th class="text-end">{{ __('Attended') }}</th><th>{{ __('Status') }}</th></tr></thead>
                    <tbody>
                        @forelse ($suggestions as $suggestion)
                            <tr wire:key="sg-{{ $suggestion['meeting_attendance_id'] }}">
                                <td class="small">{{ $suggestion['identifier'] }}</td>
                                <td>
                                    @if ($suggestion['student_id'])
                                        {{ $students->get($suggestion['student_id'])?->first_name }} {{ $students->get($suggestion['student_id'])?->last_name }}
                                    @else
                                        <span class="badge bg-label-warning">{{ __('unmatched') }}</span>
                                    @endif
                                </td>
                                <td class="text-end">
                                    {{ $suggestion['percent'] !== null ? $suggestion['percent'].'%' : '—' }}
                                    @if ($suggestion['below']) <span class="badge bg-label-danger">{{ __('below threshold') }}</span> @endif
                                </td>
                                <td>
                                    @if ($suggestion['student_id'])
                                        <select class="form-select form-select-sm" wire:model="decisions.{{ $suggestion['meeting_attendance_id'] }}">
                                            <option value="">{{ __('— decide —') }}</option>
                                            <option value="present">{{ __('Present') }}</option>
                                            <option value="late">{{ __('Late') }}</option>
                                            <option value="absent">{{ __('Absent') }}</option>
                                            <option value="excused">{{ __('Excused') }}</option>
                                        </select>
                                    @else
                                        <span class="small text-body-secondary">{{ __('Mark from the register') }}</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="text-center text-body-secondary py-3">{{ __('No participants recorded for this lesson.') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        @if ($conflicts !== [])
            <div class="alert alert-warning small mt-3">{{ __(':n learner(s) already had a different mark in the register; those marks were not changed.', ['n' => count($conflicts)]) }}</div>
        @endif

        @if ($suggestions !== [])
            <button type="button" class="btn btn-primary btn-sm mt-3" wire:click="confirm" wire:confirm="{{ __('Write these statuses to the class register?') }}">{{ __('Confirm in register') }}</button>
        @endif
    @endif
</div>
