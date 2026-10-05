<?php

declare(strict_types=1);

namespace Modules\Comms\Livewire\Events;

use App\Concerns\Toasts;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use InvalidArgumentException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Comms\Domain\Actions\CancelEventRegistrationAction;
use Modules\Comms\Domain\Actions\ConfirmEventTicketPaymentAction;
use Modules\Comms\Domain\Actions\CreateEventRegistrationAction;
use Modules\Comms\Domain\Actions\RegisterForEventAction;
use Modules\Comms\Domain\DataObjects\RegisterForEventData;
use Modules\Comms\Domain\Exceptions\TicketingRequiresFeeComponentException;
use Modules\Comms\Domain\Exceptions\TicketRequiresStudentAccountException;
use Modules\Comms\Models\CalendarEvent;
use Modules\Comms\Models\EventAttendee;
use Modules\Comms\Models\EventRegistration;
use Modules\Core\Domain\Support\Currency;
use Modules\Core\Domain\Support\Money;
use Modules\Core\Livewire\Concerns\AuthorizesPermissions;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Models\School;
use Modules\Finance\Models\FeeComponent;
use Modules\Finance\Models\Receipt;
use Modules\People\Models\Student;

/**
 * `Comms\Events\Register` (Book I COM-06 §4, `events.manage` ⚠). Turn
 * a calendar event into a registration (capacity, optional ticket),
 * register attendees, cancel them, and confirm ticket payment.
 *
 * A full event never fails silently: the Action returns a waitlisted
 * row and the screen says so (BR-COM-06-007, AC-COM-06-004); a
 * cancellation promotes the next person automatically. A ticketed
 * event raises a real FIN-02 ad hoc charge, which the Action only
 * supports for a *student* attendee. Payment is confirmed by entering
 * a receipt number already issued at the cashier — the backend's own
 * documented bridge for the missing charge-to-receipt settlement, so
 * "paid" here is a human-asserted fact, not system-derived.
 */
#[Title('Event registration')]
#[Layout('layouts.app')]
final class Register extends Component
{
    use AuthorizesPermissions;
    use InteractsWithSchool;
    use Toasts;

    public ?int $calendarEventId = null;

    public string $capacity = '';

    public bool $requiresTicket = false;

    public string $ticketPrice = '';

    public ?int $feeComponentId = null;

    public string $rsvpDeadline = '';

    public ?int $registrationId = null;

    public string $attendeeType = 'guardian';

    public string $attendeeName = '';

    public int $partySize = 1;

    public string $admissionNumber = '';

    public string $receiptNumber = '';

    public function mount(School $school): void
    {
        $this->loadSchool($school);
        $this->authorizePermission('events.manage');
    }

    public function createRegistration(): void
    {
        $this->authorizePermission('events.manage');

        $this->validate([
            'calendarEventId' => ['required', 'integer'],
            'capacity' => ['nullable', 'integer', 'min:1', 'max:32000'],
            'ticketPrice' => [$this->requiresTicket ? 'required' : 'nullable', 'regex:/^\d+(\.\d{1,2})?$/'],
            'rsvpDeadline' => ['nullable', 'date'],
        ], ['ticketPrice.regex' => __('Enter an amount like 5 or 5.50.')]);

        $event = CalendarEvent::where('school_id', $this->school->id)->findOrFail($this->calendarEventId);

        if (EventRegistration::where('calendar_event_id', $event->id)->exists()) {
            $this->addError('calendarEventId', __('That event already has a registration.'));

            return;
        }

        $currency = Currency::from($this->school->base_currency);

        if ($this->requiresTicket) {
            $component = $this->feeComponentId !== null
                ? FeeComponent::where('school_id', $this->school->id)->find($this->feeComponentId)
                : null;

            if ($component === null) {
                $this->addError('feeComponentId', __('A ticketed event needs a fee component for its income.'));

                return;
            }
        }

        try {
            $registration = app(CreateEventRegistrationAction::class)->execute(
                schoolId: $this->school->id,
                calendarEventId: $event->id,
                capacity: $this->capacity !== '' ? (int) $this->capacity : null,
                requiresTicket: $this->requiresTicket,
                ticketPriceMinor: $this->requiresTicket ? Money::fromDecimal($this->ticketPrice, $currency)->minor : null,
                ticketCurrency: $this->requiresTicket ? $currency->value : null,
                feeComponentId: $this->requiresTicket ? $this->feeComponentId : null,
                rsvpDeadline: $this->rsvpDeadline !== '' ? Carbon::parse($this->rsvpDeadline) : null,
            );
        } catch (TicketingRequiresFeeComponentException $exception) {
            $this->addError('feeComponentId', $exception->getMessage());

            return;
        }

        $this->registrationId = $registration->id;
        $this->reset(['calendarEventId', 'capacity', 'requiresTicket', 'ticketPrice', 'feeComponentId', 'rsvpDeadline']);
        $this->toast(__('Registration opened.'));
    }

    public function registerAttendee(): void
    {
        $this->authorizePermission('events.manage');

        $this->validate([
            'registrationId' => ['required', 'integer'],
            'attendeeType' => ['required', 'in:guardian,staff,student,external'],
            'attendeeName' => ['required', 'string', 'max:200'],
            'partySize' => ['required', 'integer', 'min:1', 'max:20'],
        ]);

        $registration = EventRegistration::where('school_id', $this->school->id)->findOrFail($this->registrationId);

        $student = null;

        if ($this->admissionNumber !== '') {
            $student = Student::where('school_id', $this->school->id)->where('admission_number', $this->admissionNumber)->first();

            if ($student === null) {
                $this->addError('admissionNumber', __('No learner has that admission number.'));

                return;
            }
        }

        try {
            $result = app(RegisterForEventAction::class)->execute(new RegisterForEventData(
                schoolId: $this->school->id,
                registrationId: $registration->id,
                attendeeType: $this->attendeeType,
                attendeeName: $this->attendeeName,
                studentId: $student?->id,
                partySize: $this->partySize,
                raisedByUserId: (int) auth()->id(),
            ));
        } catch (TicketRequiresStudentAccountException|InvalidArgumentException $exception) {
            $this->addError('admissionNumber', __('This event is ticketed: enter the learner’s admission number so the ticket can be charged to their account.'));

            return;
        }

        $this->reset(['attendeeName', 'partySize', 'admissionNumber']);
        $this->toast($result->waitlisted
            ? __('The event is full — added to the waitlist at position :n.', ['n' => $result->waitlistPosition])
            : __('Attendee registered.'), $result->waitlisted ? 'warning' : 'success');
    }

    public function cancelAttendee(int $attendeeId): void
    {
        $this->authorizePermission('events.manage');

        $attendee = EventAttendee::where('school_id', $this->school->id)->findOrFail($attendeeId);
        app(CancelEventRegistrationAction::class)->execute($attendee->id);

        $this->toast(__('Registration cancelled. The next person on the waitlist, if any, has been promoted.'));
    }

    public function confirmPayment(int $attendeeId): void
    {
        $this->authorizePermission('events.manage');

        $this->validate(['receiptNumber' => ['required', 'string', 'max:60']]);

        $attendee = EventAttendee::where('school_id', $this->school->id)->findOrFail($attendeeId);
        $receipt = Receipt::where('school_id', $this->school->id)->where('receipt_number', $this->receiptNumber)->first();

        if ($receipt === null) {
            $this->addError('receiptNumber', __('No receipt has that number.'));

            return;
        }

        app(ConfirmEventTicketPaymentAction::class)->execute($attendee->id, $receipt->id);

        $this->reset('receiptNumber');
        $this->toast(__('Ticket payment recorded against the receipt.'));
    }

    public function render(): View
    {
        return view('comms::events.register', [
            'events' => CalendarEvent::where('school_id', $this->school->id)
                ->where('starts_at', '>=', now()->subDay())
                ->whereDoesntHave('registrations')
                ->orderBy('starts_at')->limit(100)->get(['id', 'title', 'starts_at']),
            'registrations' => EventRegistration::with('calendarEvent:id,title,starts_at')->where('school_id', $this->school->id)->orderByDesc('id')->limit(50)->get(),
            'attendees' => $this->registrationId !== null
                ? EventAttendee::where('school_id', $this->school->id)->where('registration_id', $this->registrationId)->orderBy('id')->get()
                : collect(),
            'feeComponents' => FeeComponent::where('school_id', $this->school->id)->where('is_active', true)->orderBy('name')->get(['id', 'name']),
        ]);
    }
}
