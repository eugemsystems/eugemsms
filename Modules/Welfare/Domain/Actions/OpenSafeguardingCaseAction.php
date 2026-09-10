<?php

declare(strict_types=1);

namespace Modules\Welfare\Domain\Actions;

use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Modules\Core\Domain\Actions\Action;
use Modules\Core\Domain\Actions\Documents\AllocateNumberAction;
use Modules\Core\Domain\DataObjects\Documents\AllocateNumberData;
use Modules\People\Models\Student;
use Modules\Welfare\Domain\DataObjects\OpenSafeguardingCaseData;
use Modules\Welfare\Domain\Events\CaseOpened;
use Modules\Welfare\Models\SafeguardingCase;
use Modules\Welfare\Models\SafeguardingConcern;

/**
 * ACT-OpenSafeguardingCase (Book G BRD-08 §2/§4/BR-BRD-08-013/017/
 * 020/AC-BRD-08-009). `case_reference` is gapless via `CORE-06`.
 * `guardians_informed` may legitimately be `false` — with a mandatory
 * encrypted reason — because sometimes the parent is the risk. This
 * action also sets the learner's `has_safeguarding_flag` (`PPL-01`) to
 * existence-only, and sets a far-longer-than-general `retention_until`
 * from `safeguarding.retention_years_after_exit`... this pass leaves
 * `retention_until` null (no exit date exists to count from before the
 * learner actually withdraws) — a real value requires `PPL-01`'s own
 * withdrawal date, computed at that point, not at case-open time.
 */
final class OpenSafeguardingCaseAction extends Action
{
    public function __construct(
        private readonly AllocateNumberAction $allocateNumber,
    ) {}

    public function execute(OpenSafeguardingCaseData $data): SafeguardingCase
    {
        if ($data->guardiansInformed === false && ($data->guardiansNotInformedReason === null || trim($data->guardiansNotInformedReason) === '')) {
            throw ValidationException::withMessages([
                'guardiansNotInformedReason' => 'A reason is required when guardians are not informed (BR-BRD-08-013).',
            ]);
        }

        $number = $this->allocateNumber->execute(new AllocateNumberData(
            schoolId: $data->schoolId,
            documentType: 'safeguarding_case',
            allocatedByUserId: $data->openedByUserId,
        ));

        return $this->transaction(function () use ($data, $number): SafeguardingCase {
            $case = SafeguardingCase::create([
                'school_id' => $data->schoolId,
                'case_reference' => $number->formatted_number,
                'student_id' => $data->studentId,
                'opened_at' => Carbon::now(),
                'opened_by' => $data->openedByUserId,
                'lead_staff_id' => $data->leadStaffId,
                'category' => $data->category,
                'risk_level' => $data->riskLevel,
                'summary' => $data->summary,
                'status' => 'open',
                'external_agency_involved' => false,
                'guardians_informed' => $data->guardiansInformed,
                'guardians_not_informed_reason' => $data->guardiansNotInformedReason,
            ]);

            if ($data->concernId !== null) {
                SafeguardingConcern::whereKey($data->concernId)->update(['case_id' => $case->id]);
            }

            Student::whereKey($data->studentId)->update(['has_safeguarding_flag' => true]);

            event(new CaseOpened($case));

            return $case;
        });
    }
}
