<?php

declare(strict_types=1);

namespace Modules\Comms\Livewire\Events;

use App\Concerns\Toasts;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Comms\Domain\Actions\CheckInEventAttendeeAction;
use Modules\Comms\Domain\Exceptions\TicketNotPaidException;
use Modules\Comms\Models\EventAttendee;
use Modules\Comms\Models\EventRegistration;
use Modules\Core\Livewire\Concerns\AuthorizesPermissions;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Models\School;

/**
 * `Comms\Events\CheckIn` (Book I COM-06 §4, `events.checkin`). Door
 * check-in by name search. A ticketed event refuses an unpaid attendee
 * (AC-COM-06-003) — the Action throws and this screen shows the reason
 * rather than checking them in. Waitlisted and cancelled people are
 * never listed: they hold no seat. Scanning a ticket is the mobile
 * app's job, not this screen's.
 */
#[Title('Event check-in')]
#[Layout('layouts.app')]
final class CheckIn extends Component
{
    use AuthorizesPermissions;
    use InteractsWithSchool;
    use Toasts;

    public ?int $registrationId = null;

    public string $search = '';

    public function mount(School $school): void
    {
        $this->loadSchool($school);
        $this->authorizePermission('events.checkin');
    }

    public function checkIn(int $attendeeId): void
    {
        $this->authorizePermission('events.checkin');

        $attendee = EventAttendee::where('school_id', $this->school->id)->where('registration_id', $this->registrationId)->findOrFail($attendeeId);

        try {
            app(CheckInEventAttendeeAction::class)->execute($attendee->id);
        } catch (TicketNotPaidException $exception) {
            $this->toast($exception->getMessage(), 'danger');

            return;
        }

        $this->toast(__(':name checked in.', ['name' => $attendee->attendee_name]));
    }

    public function render(): View
    {
        $registrations = EventRegistration::with('calendarEvent:id,title,starts_at')
            ->where('school_id', $this->school->id)
            ->orderByDesc('id')->limit(50)->get();

        $attendees = $this->registrationId !== null
            ? EventAttendee::where('school_id', $this->school->id)
                ->where('registration_id', $this->registrationId)
                ->whereIn('status', ['registered', 'paid', 'checked_in'])
                ->when($this->search !== '', fn ($query) => $query->where('attendee_name', 'like', '%'.$this->search.'%'))
                ->orderBy('attendee_name')->limit(200)->get()
            : collect();

        return view('comms::events.check-in', [
            'registrations' => $registrations,
            'attendees' => $attendees,
            'requiresTicket' => (bool) $registrations->firstWhere('id', $this->registrationId)?->requires_ticket,
        ]);
    }
}
