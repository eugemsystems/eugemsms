<?php

declare(strict_types=1);

namespace Modules\Comms\Domain\Actions;

use InvalidArgumentException;
use Modules\Comms\Domain\DataObjects\RegisterForEventData;
use Modules\Comms\Domain\DataObjects\RegisterForEventResult;
use Modules\Comms\Domain\Exceptions\TicketRequiresStudentAccountException;
use Modules\Comms\Models\EventAttendee;
use Modules\Comms\Models\EventRegistration;
use Modules\Core\Domain\Actions\Action;
use Modules\Core\Domain\Support\SessionContext;
use Modules\Core\Models\School;
use Modules\Finance\Domain\Actions\CreateAdHocChargeAction;
use Modules\Finance\Domain\DataObjects\CreateAdHocChargeData;

/**
 * ACT-RegisterForEvent (Book I COM-06 §3 ⭐/BR-COM-06-006/007
 * (AC-COM-06-003/004)). Capacity is checked and the row created under
 * a `lockForUpdate` on the registration itself — the race BRD-01's own
 * waitlist pattern leaves as the caller's responsibility (confirmed:
 * no lock exists there to mirror) is closed here instead of copied.
 * Beyond capacity, this ALWAYS returns a real, waitlisted row — never
 * an exception — the literal "offers a waitlist instead of a silent
 * failure" AC-COM-06-004 asks for.
 */
final class RegisterForEventAction extends Action
{
    public function __construct(
        private readonly CreateAdHocChargeAction $createAdHocCharge,
    ) {}

    public function execute(RegisterForEventData $data): RegisterForEventResult
    {
        return $this->transaction(function () use ($data): RegisterForEventResult {
            $registration = EventRegistration::where('id', $data->registrationId)->lockForUpdate()->firstOrFail();

            if ($registration->isFull()) {
                $nextPosition = (int) EventAttendee::where('registration_id', $registration->id)
                    ->where('status', 'waitlisted')
                    ->max('waitlist_position') + 1;

                $attendee = EventAttendee::create([
                    'school_id' => $data->schoolId,
                    'registration_id' => $registration->id,
                    'attendee_type' => $data->attendeeType,
                    'attendee_name' => $data->attendeeName,
                    'guardian_id' => $data->guardianId,
                    'party_size' => $data->partySize,
                    'waitlist_position' => $nextPosition,
                    'status' => 'waitlisted',
                ]);

                return new RegisterForEventResult($attendee, waitlisted: true, waitlistPosition: $nextPosition);
            }

            $attendee = EventAttendee::create([
                'school_id' => $data->schoolId,
                'registration_id' => $registration->id,
                'attendee_type' => $data->attendeeType,
                'attendee_name' => $data->attendeeName,
                'guardian_id' => $data->guardianId,
                'party_size' => $data->partySize,
                'status' => 'registered',
            ]);

            $registration->increment('registered_count');

            if ($registration->requires_ticket) {
                $this->raiseTicketCharge($registration, $attendee, $data);
            }

            return new RegisterForEventResult($attendee, waitlisted: false);
        });
    }

    private function raiseTicketCharge(EventRegistration $registration, EventAttendee $attendee, RegisterForEventData $data): void
    {
        if ($data->studentId === null) {
            throw TicketRequiresStudentAccountException::forAttendeeType($data->attendeeType);
        }

        if ($data->raisedByUserId === null) {
            throw new InvalidArgumentException('RegisterForEventData::$raisedByUserId is required when the registration requires a ticket.');
        }

        $calendarEvent = $registration->calendarEvent;
        $currency = $data->feeCurrency ?? $registration->ticket_currency ?? School::findOrFail($data->schoolId)->base_currency;

        $charge = $this->createAdHocCharge->execute(new CreateAdHocChargeData(
            schoolId: $data->schoolId,
            academicYearId: $calendarEvent->academic_year_id,
            termId: $calendarEvent->term_id ?? SessionContext::termId(),
            studentId: $data->studentId,
            componentId: $registration->fee_component_id,
            description: "Event ticket — {$calendarEvent->title}",
            unitRateMinor: $registration->ticket_price_minor,
            currency: $currency,
            raisedByUserId: $data->raisedByUserId,
            sourceType: 'event_registration',
            sourceId: $attendee->id,
        ));

        $attendee->update(['ad_hoc_charge_id' => $charge->id]);
    }
}
