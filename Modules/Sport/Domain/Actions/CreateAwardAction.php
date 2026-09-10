<?php

declare(strict_types=1);

namespace Modules\Sport\Domain\Actions;

use Modules\Core\Domain\Actions\Action;
use Modules\Sport\Domain\DataObjects\CreateAwardData;
use Modules\Sport\Models\Award;

/**
 * ACT-CreateAward (Book H2 OPS-07 §2/BR-OPS-07-010). "Carries into
 * the alumni record on graduation" is a documented Book K deferral —
 * no alumni module exists yet in this codebase.
 */
final class CreateAwardAction extends Action
{
    public function execute(CreateAwardData $data): Award
    {
        return $this->transaction(fn (): Award => Award::create([
            'school_id' => $data->schoolId,
            'academic_year_id' => $data->academicYearId,
            'student_id' => $data->studentId,
            'award_type' => $data->awardType,
            'activity_id' => $data->activityId,
            'title' => $data->title,
            'citation' => $data->citation,
            'awarded_on' => $data->awardedOn->toDateString(),
            'awarded_by' => $data->awardedByUserId,
            'appears_on_report_card' => $data->appearsOnReportCard,
            'appears_on_transcript' => $data->appearsOnTranscript,
        ]));
    }
}
