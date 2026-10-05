<?php

declare(strict_types=1);

namespace Modules\Facilities\Livewire\Request;

use App\Concerns\Toasts;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Core\Domain\Support\SessionContext;
use Modules\Core\Livewire\Concerns\AuthorizesPermissions;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Livewire\Sessions\Concerns\InteractsWithSession;
use Modules\Core\Models\School;
use Modules\Facilities\Domain\Actions\CancelBookingAction;
use Modules\Facilities\Domain\Actions\CheckResourceAvailabilityAction;
use Modules\Facilities\Domain\Actions\ExpandRecurringBookingAction;
use Modules\Facilities\Domain\Actions\RequestBookingAction;
use Modules\Facilities\Domain\DataObjects\RequestBookingData;
use Modules\Facilities\Domain\Exceptions\ResourceNotAvailableException;
use Modules\Facilities\Models\BookableResource;
use Modules\Facilities\Models\ResourceBooking;

/**
 * `Request\Index` (Book H2 OPS-05 §4 ⭐/BR-OPS-05-001/002/008,
 * `facilities.book`). The clash — against another booking's own
 * setup/cleanup buffer, or against `ACA-03`'s published timetable for
 * a venue-linked resource — shows live before the request is even
 * submitted, and `RequestBookingAction` itself refuses the same way
 * on submit if the picture changed underneath.
 */
#[Title('Booking request')]
#[Layout('layouts.app')]
final class Index extends Component
{
    use AuthorizesPermissions;
    use InteractsWithSchool;
    use InteractsWithSession;
    use Toasts;

    public ?int $resourceId = null;

    public string $bookingType = 'internal';

    public string $purpose = '';

    public string $startsAt = '';

    public string $endsAt = '';

    public ?string $hirerName = null;

    public ?string $hirerContact = null;

    public ?string $hirerOrganisation = null;

    public ?string $clashReason = null;

    public ?string $recurrenceFrequency = null;

    public int $recurrenceOccurrences = 1;

    public function mount(School $school): void
    {
        $this->loadSchool($school);
        $this->loadSessionContext($school);
        $this->authorizePermission('facilities.book');
    }

    public function checkAvailability(): void
    {
        $this->clashReason = null;

        if ($this->resourceId === null || $this->startsAt === '' || $this->endsAt === '') {
            return;
        }

        $this->clashReason = app(CheckResourceAvailabilityAction::class)->execute(
            (int) $this->resourceId,
            Carbon::parse($this->startsAt),
            Carbon::parse($this->endsAt),
        );
    }

    public function request(): void
    {
        $this->validate([
            'resourceId' => ['required', 'integer'],
            'bookingType' => ['required', 'string'],
            'purpose' => ['required', 'string'],
            'startsAt' => ['required'],
            'endsAt' => ['required'],
        ]);

        try {
            $booking = app(RequestBookingAction::class)->execute(new RequestBookingData(
                schoolId: $this->school->id,
                termId: (int) SessionContext::termId(),
                resourceId: (int) $this->resourceId,
                bookingType: $this->bookingType,
                purpose: $this->purpose,
                startsAt: Carbon::parse($this->startsAt),
                endsAt: Carbon::parse($this->endsAt),
                requestedByUserId: (int) auth()->id(),
                hirerName: $this->bookingType === 'external' ? $this->hirerName : null,
                hirerContact: $this->bookingType === 'external' ? $this->hirerContact : null,
                hirerOrganisation: $this->bookingType === 'external' ? $this->hirerOrganisation : null,
            ));
        } catch (ResourceNotAvailableException $e) {
            $this->toast($e->getMessage(), 'danger');

            return;
        }

        if ($this->recurrenceFrequency !== null && $this->recurrenceOccurrences > 1) {
            try {
                app(ExpandRecurringBookingAction::class)->execute($booking->id, $this->recurrenceFrequency, $this->recurrenceOccurrences, (int) auth()->id());
            } catch (ValidationException $e) {
                $this->toast($e->getMessage(), 'danger');

                return;
            }
        }

        $this->reset(['purpose', 'startsAt', 'endsAt', 'hirerName', 'hirerContact', 'hirerOrganisation', 'clashReason', 'recurrenceFrequency', 'recurrenceOccurrences']);
        $this->recurrenceOccurrences = 1;
        $this->toast(__('Booking requested.'));
    }

    public function cancel(int $bookingId): void
    {
        app(CancelBookingAction::class)->execute($bookingId, 'Cancelled from the booking request screen.');
        $this->toast(__('Booking cancelled.'));
    }

    public function render(): View
    {
        return view('facilities::request.index', [
            'resources' => BookableResource::where('school_id', $this->school->id)->orderBy('code')->get(),
            'bookings' => ResourceBooking::with('resource')->where('school_id', $this->school->id)->orderByDesc('starts_at')->limit(30)->get(),
        ]);
    }
}
