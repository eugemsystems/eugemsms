<?php

declare(strict_types=1);

namespace Modules\People\Domain\Actions;

use Illuminate\Support\Carbon;
use Modules\Core\Domain\Actions\Action;
use Modules\Core\Domain\Actions\Documents\AllocateNumberAction;
use Modules\Core\Domain\DataObjects\Documents\AllocateNumberData;
use Modules\People\Domain\DataObjects\ReportDisciplinaryCaseData;
use Modules\People\Models\StaffDisciplinaryCase;

/**
 * ACT-ReportDisciplinaryCase (Book C PPL-04 §4/BR-PPL-04-020).
 * `isConfidential` defaults `true` on the DTO itself, matching
 * "confidential by default" — a caller has to actively opt out.
 */
final class ReportDisciplinaryCaseAction extends Action
{
    public function __construct(
        private readonly AllocateNumberAction $allocateNumber,
    ) {}

    public function execute(ReportDisciplinaryCaseData $data): StaffDisciplinaryCase
    {
        return $this->transaction(function () use ($data): StaffDisciplinaryCase {
            $number = $this->allocateNumber->execute(new AllocateNumberData(
                schoolId: $data->schoolId,
                documentType: 'disciplinary_case',
                allocatedByUserId: $data->reportedByUserId,
            ));

            return StaffDisciplinaryCase::create([
                'school_id' => $data->schoolId,
                'staff_id' => $data->staffId,
                'case_number' => $number->formatted_number,
                'category' => $data->category,
                'description' => $data->description,
                'incident_date' => $data->incidentDate->toDateString(),
                'reported_by' => $data->reportedByUserId,
                'stage' => 'reported',
                'is_confidential' => $data->isConfidential,
                'created_at' => Carbon::now(),
            ]);
        });
    }
}
