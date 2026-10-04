<?php

declare(strict_types=1);

namespace Modules\Academic\Domain\Actions;

use Modules\Academic\Domain\DataObjects\CreateTimetableData;
use Modules\Academic\Models\Timetable;
use Modules\Core\Domain\Actions\Action;

/**
 * ACT-CreateTimetable (Book E admin-UI pass, ACA-03 §2). The spec's own
 * `timetables` table and every downstream action
 * (`GenerateTimetableAction`, `CreateTimetableSlotAction`,
 * `PublishTimetableAction`) all take an existing `timetable_id` — but no
 * Action anywhere ever created the parent row itself (verified: every
 * test fixture used `Timetable::factory()->create()` directly). The same
 * gap this pass found and closed in `ACA-01`'s catalogue
 * (`CreateCurriculumFrameworkAction` et al. — see `.ai/rules/academic.md`).
 * A plain `Model::create()` wrapped in `$this->transaction()`, status
 * always `draft` — no business logic beyond what the migration already
 * enforces.
 */
final class CreateTimetableAction extends Action
{
    public function execute(CreateTimetableData $data): Timetable
    {
        return $this->transaction(fn (): Timetable => Timetable::create([
            'school_id' => $data->schoolId,
            'academic_year_id' => $data->academicYearId,
            'term_id' => $data->termId,
            'structure_id' => $data->structureId,
            'name' => $data->name,
            'version' => 1,
            'status' => 'draft',
            'hard_violations' => 0,
            'soft_violations' => 0,
            'created_by' => $data->createdByUserId,
        ]));
    }
}
