<?php

declare(strict_types=1);

namespace Modules\Comms\Domain\Actions;

use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Modules\Comms\Models\CalendarEvent;
use Modules\Core\Domain\Actions\Action;

/**
 * ACT-CreateCalendarEvent (Book I COM-06 §2). For an event with no
 * owning module to rebuild it from — a fun fair, an open day — staff
 * create it directly. `source_type = 'manual'`/`source_module_id =
 * null` keeps it outside `RebuildCalendarAction`'s reconciliation
 * entirely, so a nightly rebuild never deletes a manually-created
 * event for having no registered source (see that action's own
 * docblock).
 */
final class CreateCalendarEventAction extends Action
{
    public function execute(
        int $schoolId,
        int $academicYearId,
        string $title,
        CarbonInterface $startsAt,
        string $audienceScope = 'whole_school',
        ?int $termId = null,
        ?string $description = null,
        ?CarbonInterface $endsAt = null,
        bool $isAllDay = false,
        ?string $location = null,
        ?int $audienceScopeId = null,
        bool $isPublic = true,
    ): CalendarEvent {
        return $this->transaction(fn (): CalendarEvent => CalendarEvent::create([
            'school_id' => $schoolId,
            'academic_year_id' => $academicYearId,
            'term_id' => $termId,
            'source_type' => 'manual',
            'source_module_id' => null,
            'title' => $title,
            'description' => $description,
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'is_all_day' => $isAllDay,
            'location' => $location,
            'audience_scope' => $audienceScope,
            'audience_scope_id' => $audienceScopeId,
            'is_public' => $isPublic,
            'rebuilt_at' => Carbon::now(),
        ]));
    }
}
