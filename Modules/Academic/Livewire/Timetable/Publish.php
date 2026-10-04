<?php

declare(strict_types=1);

namespace Modules\Academic\Livewire\Timetable;

use App\Concerns\Toasts;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Academic\Domain\Actions\GenerateAttendanceSessionsFromTimetableAction;
use Modules\Academic\Domain\Actions\PublishTimetableAction;
use Modules\Academic\Domain\DataObjects\GenerateAttendanceSessionsFromTimetableData;
use Modules\Academic\Domain\DataObjects\PublishTimetableData;
use Modules\Academic\Domain\Support\TimetableClashDetector;
use Modules\Academic\Models\Timetable;
use Modules\Academic\Models\TimetableSlot;
use Modules\Core\Domain\Exceptions\DomainException;
use Modules\Core\Livewire\Concerns\AuthorizesPermissions;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Models\School;

/**
 * `Timetable\Publish` (Book E ACA-03 §6/BR-ACA-03-013/014/015,
 * `academic.timetable.publish` ⚠). One lifecycle screen: shows the live
 * clash count (`PublishTimetableAction` refuses while any exists —
 * `AC-ACA-03-003`), publishes, then runs the explicit, separate
 * attendance-session-generation follow-up call for the next N days
 * (`timetable.session_generation_days_ahead`, default 7 —
 * `AC-ACA-03-005`) so a caller controls the window, matching
 * `PublishTimetableAction`'s own documented boundary.
 */
#[Title('Publish timetable')]
#[Layout('layouts.app')]
final class Publish extends Component
{
    use AuthorizesPermissions;
    use InteractsWithSchool;
    use Toasts;

    public Timetable $timetable;

    public string $sessionDays = '7';

    public function mount(School $school, Timetable $timetable): void
    {
        $this->loadSchool($school);
        $this->authorizePermission('academic.timetable.view');

        abort_unless($timetable->school_id === $school->id, 404);

        $this->timetable = $timetable;
    }

    public function publish(): void
    {
        $this->authorizePermission('academic.timetable.publish');

        try {
            $published = app(PublishTimetableAction::class)->execute(new PublishTimetableData(
                timetableId: $this->timetable->id,
                publishedByUserId: (int) Auth::id(),
            ));
        } catch (DomainException $e) {
            $this->toast($e->getMessage(), 'danger');

            return;
        }

        $this->timetable = $published;
        $this->toast(__('Timetable published. Prior published version for this term is now superseded.'));
    }

    public function generateSessions(): void
    {
        $this->authorizePermission('academic.timetable.publish');

        $days = max(1, (int) $this->sessionDays);

        $result = app(GenerateAttendanceSessionsFromTimetableAction::class)->execute(new GenerateAttendanceSessionsFromTimetableData(
            timetableId: $this->timetable->id,
            fromDate: Carbon::now(),
            toDate: Carbon::now()->addDays($days),
        ));

        $this->toast(__(':created session(s) created, :skipped already existed.', ['created' => $result['created'], 'skipped' => $result['skipped']]));
    }

    public function render(): View
    {
        $slots = TimetableSlot::where('timetable_id', $this->timetable->id)->get();
        $clashes = app(TimetableClashDetector::class)->detect($slots);

        return view('academic::timetable.publish', [
            'clashCount' => $clashes->count(),
        ]);
    }
}
