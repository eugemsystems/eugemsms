<?php

declare(strict_types=1);

namespace Modules\Welfare\Domain\Actions;

use Modules\Core\Domain\Actions\Action;
use Modules\Welfare\Domain\DataObjects\RecordHealthScreeningData;
use Modules\Welfare\Models\HealthScreening;

/**
 * ACT-RecordHealthScreening (Book G BRD-06 §2). `results` is
 * `SecondaryEncrypted`; the caller's array is `json_encode`d before
 * the cast encrypts it, since a JSON cast cannot layer over an
 * encryption cast.
 */
final class RecordHealthScreeningAction extends Action
{
    public function execute(RecordHealthScreeningData $data): HealthScreening
    {
        return $this->transaction(fn (): HealthScreening => HealthScreening::create([
            'school_id' => $data->schoolId,
            'term_id' => $data->termId,
            'student_id' => $data->studentId,
            'screening_type' => $data->screeningType,
            'screened_on' => $data->screenedOn->toDateString(),
            'results' => $data->results === null ? null : json_encode($data->results, JSON_THROW_ON_ERROR),
            'outcome' => $data->outcome,
            'screened_by' => $data->screenedBy,
        ]));
    }
}
