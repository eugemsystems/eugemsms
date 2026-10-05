<div>
    <h4 class="mb-1">{{ __('Event registration') }} ⚠</h4>
    <p class="text-body-secondary small">{{ __('Open registration for a calendar event, then register attendees. A full event offers a waitlist; a cancellation promotes the next person. Ticket payment is confirmed against a receipt already issued at the cashier.') }}</p>

    <div class="row g-4">
        <div class="col-lg-7">
            <div class="card mb-4">
                <div class="card-header">{{ __('Registrations') }}</div>
                <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        <thead><tr><th>{{ __('Event') }}</th><th class="text-end">{{ __('Registered') }}</th><th class="text-end">{{ __('Capacity') }}</th><th>{{ __('Ticket') }}</th><th></th></tr></thead>
                        <tbody>
                            @forelse ($registrations as $registration)
                                <tr wire:key="reg-{{ $registration->id }}" class="{{ $registrationId === $registration->id ? 'table-active' : '' }}">
                                    <td>{{ $registration->calendarEvent?->title }}</td>
                                    <td class="text-end">{{ $registration->registered_count }}</td>
                                    <td class="text-end">{{ $registration->capacity ?? '∞' }}</td>
                                    <td>{{ $registration->requires_ticket ? \Modules\Core\Domain\Support\Money::of((int) $registration->ticket_price_minor, \Modules\Core\Domain\Support\Currency::from($registration->ticket_currency ?? 'USD'))->format() : '—' }}</td>
                                    <td class="text-end"><button type="button" class="btn btn-sm btn-outline-secondary" wire:click="$set('registrationId', {{ $registration->id }})">{{ __('Attendees') }}</button></td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="text-center text-body-secondary py-3">{{ __('No event has registration open.') }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            @if ($registrationId)
                <div class="card">
                    <div class="card-header">{{ __('Attendees') }}</div>
                    @error('receiptNumber') <div class="text-danger small px-3 pt-2">{{ $message }}</div> @enderror
                    <div class="table-responsive">
                        <table class="table table-sm mb-0">
                            <thead><tr><th>{{ __('Name') }}</th><th>{{ __('Party') }}</th><th>{{ __('Status') }}</th><th></th></tr></thead>
                            <tbody>
                                @forelse ($attendees as $attendee)
                                    <tr wire:key="att-{{ $attendee->id }}">
                                        <td>{{ $attendee->attendee_name }} <span class="text-body-secondary small">({{ $attendee->attendee_type }})</span></td>
                                        <td>{{ $attendee->party_size }}</td>
                                        <td>{{ str_replace('_', ' ', $attendee->status) }} @if ($attendee->status === 'waitlisted') (#{{ $attendee->waitlist_position }}) @endif</td>
                                        <td class="text-end">
                                            @if ($attendee->ad_hoc_charge_id && $attendee->status === 'registered')
                                                <input type="text" class="form-control form-control-sm d-inline-block w-auto" wire:model="receiptNumber" placeholder="{{ __('Receipt no.') }}">
                                                <button type="button" class="btn btn-sm btn-outline-success" wire:click="confirmPayment({{ $attendee->id }})">{{ __('Confirm paid') }}</button>
                                            @endif
                                            @if (in_array($attendee->status, ['registered', 'paid', 'waitlisted'], true))
                                                <button type="button" class="btn btn-sm btn-outline-danger" wire:click="cancelAttendee({{ $attendee->id }})" wire:confirm="{{ __('Cancel this registration?') }}">{{ __('Cancel') }}</button>
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr><td colspan="4" class="text-center text-body-secondary py-3">{{ __('Nobody registered yet.') }}</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            @endif
        </div>

        <div class="col-lg-5">
            <div class="card mb-4">
                <div class="card-header">{{ __('Open registration for an event') }}</div>
                <div class="card-body">
                    <select class="form-select mb-2" wire:model="calendarEventId">
                        <option value="">{{ __('Calendar event…') }}</option>
                        @foreach ($events as $event) <option value="{{ $event->id }}">{{ $event->title }} — {{ $event->starts_at->toDateString() }}</option> @endforeach
                    </select>
                    @error('calendarEventId') <div class="text-danger small mb-2">{{ $message }}</div> @enderror
                    <input type="number" class="form-control mb-2" wire:model="capacity" min="1" placeholder="{{ __('Capacity (blank = unlimited)') }}">
                    <input type="datetime-local" class="form-control mb-2" wire:model="rsvpDeadline" title="{{ __('RSVP deadline') }}">
                    <div class="form-check mb-2"><input class="form-check-input" type="checkbox" id="reg-ticket" wire:model.live="requiresTicket"><label class="form-check-label small" for="reg-ticket">{{ __('Requires a paid ticket') }}</label></div>
                    @if ($requiresTicket)
                        <input type="text" inputmode="decimal" class="form-control mb-2" wire:model="ticketPrice" placeholder="{{ __('Ticket price') }}">
                        @error('ticketPrice') <div class="text-danger small mb-2">{{ $message }}</div> @enderror
                        <select class="form-select mb-2" wire:model="feeComponentId">
                            <option value="">{{ __('Fee component for the income…') }}</option>
                            @foreach ($feeComponents as $component) <option value="{{ $component->id }}">{{ $component->name }}</option> @endforeach
                        </select>
                        @error('feeComponentId') <div class="text-danger small mb-2">{{ $message }}</div> @enderror
                    @endif
                    <button type="button" class="btn btn-primary btn-sm" wire:click="createRegistration">{{ __('Open registration') }}</button>
                </div>
            </div>

            @if ($registrationId)
                <div class="card">
                    <div class="card-header">{{ __('Register an attendee') }}</div>
                    <div class="card-body">
                        <select class="form-select mb-2" wire:model="attendeeType">
                            <option value="guardian">{{ __('Guardian') }}</option>
                            <option value="staff">{{ __('Staff') }}</option>
                            <option value="student">{{ __('Learner') }}</option>
                            <option value="external">{{ __('External guest') }}</option>
                        </select>
                        <input type="text" class="form-control mb-2" wire:model="attendeeName" placeholder="{{ __('Name') }}">
                        @error('attendeeName') <div class="text-danger small mb-2">{{ $message }}</div> @enderror
                        <input type="number" class="form-control mb-2" wire:model="partySize" min="1" max="20" title="{{ __('Party size') }}">
                        <input type="text" class="form-control mb-2" wire:model="admissionNumber" placeholder="{{ __('Learner admission no. (ticketed events)') }}">
                        @error('admissionNumber') <div class="text-danger small mb-2">{{ $message }}</div> @enderror
                        <button type="button" class="btn btn-primary btn-sm" wire:click="registerAttendee">{{ __('Register') }}</button>
                    </div>
                </div>
            @endif
        </div>
    </div>
</div>
