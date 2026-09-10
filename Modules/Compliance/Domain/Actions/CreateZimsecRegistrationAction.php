<?php

declare(strict_types=1);

namespace Modules\Compliance\Domain\Actions;

use Modules\Compliance\Domain\DataObjects\CreateZimsecRegistrationData;
use Modules\Compliance\Models\ZimsecRegistration;
use Modules\Core\Domain\Actions\Action;

/**
 * ACT-CreateZimsecRegistration (Book H3 CMP-01 §2). One row per exam
 * level/series — `(school_id, exam_level, exam_series)` is unique at
 * the database level.
 */
final class CreateZimsecRegistrationAction extends Action
{
    public function execute(CreateZimsecRegistrationData $data): ZimsecRegistration
    {
        return $this->transaction(fn (): ZimsecRegistration => ZimsecRegistration::create([
            'school_id' => $data->schoolId,
            'academic_year_id' => $data->academicYearId,
            'examination_session_id' => $data->examinationSessionId,
            'exam_level' => $data->examLevel,
            'exam_series' => $data->examSeries,
            'centre_number' => $data->centreNumber,
            'registration_opens_on' => $data->registrationOpensOn,
            'registration_closes_on' => $data->registrationClosesOn,
            'currency' => $data->currency,
            'status' => 'preparing',
        ]));
    }
}
