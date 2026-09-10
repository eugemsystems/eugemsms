<?php

declare(strict_types=1);

namespace Modules\Comms\Domain\Actions;

use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection;
use Modules\Comms\Domain\Support\CalendarAudienceFilter;
use Modules\Comms\Models\CalendarEvent;
use Modules\Core\Domain\Actions\Action;
use Modules\People\Models\Staff;
use Modules\People\Models\Student;

/**
 * ACT-GetCalendarForViewer (Book I COM-06 §4 ⭐/BR-COM-06-003
 * (AC-COM-06-002)). Resolves the viewer's own persona once, then
 * filters in PHP rather than pushing scope logic into the SQL — the
 * scope values `class`/`house` (see `CalendarAudienceFilter`'s own
 * docblock) don't yet have a queryable source to filter *in* SQL by.
 */
final class GetCalendarForViewerAction extends Action
{
    protected bool $transactional = false;

    public function __construct(
        private readonly CalendarAudienceFilter $audienceFilter,
    ) {}

    /**
     * @return Collection<int, CalendarEvent>
     */
    public function execute(User $user, int $schoolId, CarbonInterface $from, CarbonInterface $to): Collection
    {
        $isStaff = Staff::where('school_id', $schoolId)->where('user_id', $user->id)->exists();
        $student = Student::where('school_id', $schoolId)->where('user_id', $user->id)->first();

        $events = CalendarEvent::where('school_id', $schoolId)
            ->whereBetween('starts_at', [$from, $to])
            ->orderBy('starts_at')
            ->get();

        return $events->filter(fn (CalendarEvent $event): bool => $this->audienceFilter->isVisible(
            $event, $isStaff, $student?->section_id, $student?->grade_level_id,
        ))->values();
    }
}
