<?php

declare(strict_types=1);

namespace Modules\Boarding\Livewire\Visitors;

use App\Concerns\Toasts;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Boarding\Domain\Actions\BookVisitingDaySlotAction;
use Modules\Boarding\Domain\Actions\CreateVisitingDayAction;
use Modules\Boarding\Domain\DataObjects\BookVisitingDaySlotData;
use Modules\Boarding\Domain\DataObjects\CreateVisitingDayData;
use Modules\Boarding\Models\VisitingDay;
use Modules\Core\Domain\Exceptions\DomainException;
use Modules\Core\Livewire\Concerns\AuthorizesPermissions;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Livewire\Sessions\Concerns\InteractsWithSession;
use Modules\Core\Models\School;
use Modules\People\Models\Guardian;
use Modules\People\Models\Student;

/**
 * `Visitors\VisitingDays` (Book F BRD-03 §6, `boarding.visitor.manage`).
 * Create a visiting day, then book slots against it — overcrowding is
 * refused once a slot's `max_per_slot` party count is reached
 * (BR-BRD-03-022), enforced in `BookVisitingDaySlotAction` itself.
 */
#[Title('Visiting days')]
#[Layout('layouts.app')]
final class VisitingDays extends Component
{
    use AuthorizesPermissions;
    use InteractsWithSchool;
    use InteractsWithSession;
    use Toasts;

    public string $visitDate = '';

    public string $name = '';

    public string $startsAt = '09:00';

    public string $endsAt = '16:00';

    public ?int $maxPerSlot = null;

    public ?int $bookVisitingDayId = null;

    public ?int $bookStudentId = null;

    public ?int $bookGuardianId = null;

    public string $slotStartsAt = '';

    public function mount(School $school): void
    {
        $this->loadSchool($school);
        $this->loadSessionContext($school);
        $this->authorizePermission('boarding.visitor.manage');
    }

    public function createDay(): void
    {
        $term = $this->school->currentAcademicYear()?->currentTerm();

        if ($term === null || trim($this->name) === '' || $this->visitDate === '') {
            $this->toast(__('A term, name, and date are required.'), 'danger');

            return;
        }

        app(CreateVisitingDayAction::class)->execute(new CreateVisitingDayData(
            schoolId: $this->school->id,
            termId: $term->id,
            visitDate: Carbon::parse($this->visitDate),
            name: $this->name,
            startsAt: $this->startsAt,
            endsAt: $this->endsAt,
            maxPerSlot: $this->maxPerSlot,
        ));

        $this->reset(['visitDate', 'name', 'maxPerSlot']);
        $this->toast(__('Visiting day created.'));
    }

    public function bookSlot(): void
    {
        if ($this->bookVisitingDayId === null || $this->bookStudentId === null || $this->bookGuardianId === null || $this->slotStartsAt === '') {
            $this->toast(__('All booking fields are required.'), 'danger');

            return;
        }

        try {
            app(BookVisitingDaySlotAction::class)->execute(new BookVisitingDaySlotData(
                visitingDayId: $this->bookVisitingDayId,
                studentId: $this->bookStudentId,
                guardianId: $this->bookGuardianId,
                slotStartsAt: $this->slotStartsAt,
            ));
        } catch (DomainException $e) {
            $this->toast($e->getMessage(), 'danger');

            return;
        }

        $this->reset(['bookStudentId', 'bookGuardianId', 'slotStartsAt']);
        $this->toast(__('Slot booked.'));
    }

    public function render(): View
    {
        return view('boarding::visitors.visiting-days', [
            'days' => VisitingDay::where('school_id', $this->school->id)->with('bookings')->orderByDesc('visit_date')->get(),
            'students' => Student::where('school_id', $this->school->id)->orderBy('first_name')->limit(300)->get(),
            'guardians' => Guardian::where('school_id', $this->school->id)->orderBy('first_name')->limit(300)->get(),
        ]);
    }
}
