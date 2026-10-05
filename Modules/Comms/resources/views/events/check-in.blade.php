<div>
    <h4 class="mb-1">{{ __('Event check-in') }}</h4>
    <p class="text-body-secondary small">{{ __('Search for an attendee and check them in. On a ticketed event an unpaid attendee is refused.') }}</p>

    <div class="d-flex gap-2 mb-3">
        <select class="form-select form-select-sm w-auto" wire:model.live="registrationId">
            <option value="">{{ __('Choose an event…') }}</option>
            @foreach ($registrations as $registration)
                <option value="{{ $registration->id }}">{{ $registration->calendarEvent?->title }} — {{ $registration->calendarEvent?->starts_at?->toDateString() }}</option>
            @endforeach
        </select>
        <input type="search" class="form-control form-control-sm w-auto" wire:model.live.debounce.300ms="search" placeholder="{{ __('Search by name') }}">
    </div>

    @if ($registrationId)
        <div class="card">
            <div class="table-responsive">
                <table class="table table-sm mb-0">
                    <thead><tr><th>{{ __('Attendee') }}</th><th>{{ __('Party') }}</th><th>{{ __('Status') }}</th><th></th></tr></thead>
                    <tbody>
                        @forelse ($attendees as $attendee)
                            <tr wire:key="ci-{{ $attendee->id }}">
                                <td>{{ $attendee->attendee_name }} <span class="text-body-secondary small">({{ $attendee->attendee_type }})</span></td>
                                <td>{{ $attendee->party_size }}</td>
                                <td>
                                    <span class="badge {{ $attendee->status === 'checked_in' ? 'bg-label-success' : ($attendee->status === 'paid' ? 'bg-label-info' : 'bg-label-secondary') }}">{{ str_replace('_', ' ', $attendee->status) }}</span>
                                    @if ($requiresTicket && $attendee->status === 'registered') <span class="small text-warning">{{ __('ticket unpaid') }}</span> @endif
                                </td>
                                <td class="text-end">
                                    @if ($attendee->status !== 'checked_in')
                                        <button type="button" class="btn btn-sm btn-primary" wire:click="checkIn({{ $attendee->id }})">{{ __('Check in') }}</button>
                                    @else
                                        <span class="small text-body-secondary">{{ $attendee->checked_in_at?->format('H:i') }}</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="text-center text-body-secondary py-3">{{ __('No matching attendees.') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    @endif
</div>
