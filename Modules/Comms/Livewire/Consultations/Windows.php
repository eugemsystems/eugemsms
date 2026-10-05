<?php

declare(strict_types=1);

namespace Modules\Comms\Livewire\Consultations;

use App\Concerns\Toasts;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Comms\Domain\Actions\CancelConsultationBookingAction;
use Modules\Comms\Domain\Actions\CreateConsultationWindowAction;
use Modules\Comms\Models\ConsultationBooking;
use Modules\Comms\Models\ConsultationWindow;
use Modules\Core\Livewire\Concerns\AuthorizesPermissions;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Models\AcademicYear;
use Modules\Core\Models\School;
use Modules\People\Models\Staff;

/**
 * `Comms\Consultations\Windows` (Book I COM-07 §5,
 * `meetings.consultation.manage`). A teacher sets availability for a
 * parents' evening; guardians book slots through the portal API, which
 * is not built here, so this screen shows what has been booked and
 * lets staff cancel a booking (which releases its slot back to the
 * pool — BR-COM-07-005). Booking is first-come on a database unique
 * index, never a check-then-write.
 */
#[Title('Consultation windows')]
#[Layout('layouts.app')]
final class Windows extends Component
{
    use AuthorizesPermissions;
    use InteractsWithSchool;
    use Toasts;

    public ?int $staffId = null;

    public string $eventName = '';

    public string $availableFrom = '';

    public string $availableTo = '';

    public int $slotDurationMinutes = 10;

    public string $bookingOpensAt = '';

    public string $bookingClosesAt = '';

    public ?int $selectedWindowId = null;

    public function mount(School $school): void
    {
        $this->loadSchool($school);
        $this->authorizePermission('meetings.consultation.manage');
    }

    public function createWindow(): void
    {
        $this->authorizePermission('meetings.consultation.manage');

        $this->validate([
            'staffId' => ['required', 'integer'],
            'eventName' => ['required', 'string', 'max:150'],
            'availableFrom' => ['required', 'date'],
            'availableTo' => ['required', 'date', 'after:availableFrom'],
            'slotDurationMinutes' => ['required', 'integer', 'min:5', 'max:60'],
            'bookingOpensAt' => ['nullable', 'date'],
            'bookingClosesAt' => ['nullable', 'date', 'after:bookingOpensAt'],
        ]);

        Staff::where('school_id', $this->school->id)->findOrFail($this->staffId);
        $term = AcademicYear::where('school_id', $this->school->id)->where('is_current', true)->first()?->currentTerm();

        if ($term === null) {
            $this->addError('eventName', __('There is no current term to attach the window to.'));

            return;
        }

        app(CreateConsultationWindowAction::class)->execute(
            schoolId: $this->school->id,
            termId: $term->id,
            staffId: $this->staffId,
            eventName: $this->eventName,
            availableFrom: Carbon::parse($this->availableFrom),
            availableTo: Carbon::parse($this->availableTo),
            slotDurationMinutes: $this->slotDurationMinutes,
            bookingOpensAt: $this->bookingOpensAt !== '' ? Carbon::parse($this->bookingOpensAt) : null,
            bookingClosesAt: $this->bookingClosesAt !== '' ? Carbon::parse($this->bookingClosesAt) : null,
        );

        $this->reset(['eventName', 'availableFrom', 'availableTo', 'bookingOpensAt', 'bookingClosesAt']);
        $this->toast(__('Consultation window created.'));
    }

    public function cancelBooking(int $bookingId): void
    {
        $this->authorizePermission('meetings.consultation.manage');

        $booking = ConsultationBooking::where('school_id', $this->school->id)->where('status', 'booked')->findOrFail($bookingId);
        app(CancelConsultationBookingAction::class)->execute($booking->id);

        $this->toast(__('Booking cancelled — the slot is available again.'));
    }

    public function render(): View
    {
        $windows = ConsultationWindow::withCount(['bookings as booked_count' => fn ($query) => $query->where('status', 'booked')])
            ->where('school_id', $this->school->id)
            ->orderByDesc('available_from')->limit(50)->get();

        return view('comms::consultations.windows', [
            'windows' => $windows,
            'staffNames' => Staff::where('school_id', $this->school->id)->whereIn('id', $windows->pluck('staff_id'))->get()->keyBy('id'),
            'staff' => Staff::where('school_id', $this->school->id)->orderBy('last_name')->limit(300)->get(),
            'bookings' => $this->selectedWindowId !== null
                ? ConsultationBooking::where('school_id', $this->school->id)->where('window_id', $this->selectedWindowId)->orderBy('slot_starts_at')->get()
                : collect(),
        ]);
    }
}
