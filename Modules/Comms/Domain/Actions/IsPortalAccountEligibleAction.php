<?php

declare(strict_types=1);

namespace Modules\Comms\Domain\Actions;

use Modules\Core\Domain\Actions\Action;
use Modules\Core\Domain\Support\Settings\ScopeChain;
use Modules\Core\Domain\Support\Settings\SettingResolver;
use Modules\People\Models\Student;

/**
 * ACT-IsPortalAccountEligible (Book I COM-04 §5 ⭐/BR-CORE-05-022
 * (AC-COM-03-003)). The spec's own age-gating citation names
 * `BR-CORE-05-022` as "already established in PPL-01 and CORE-05" —
 * it wasn't: no grade-minimum setting or gating check exists anywhere
 * in this codebase before this pass. This is the first real
 * implementation, not a re-derivation of something Book A already
 * built — `portal.learner_min_grade_ordinal` is a genuinely new
 * setting this module introduces.
 */
final class IsPortalAccountEligibleAction extends Action
{
    public function __construct(
        private readonly SettingResolver $settings,
    ) {}

    public function execute(int $studentId): bool
    {
        $student = Student::with('gradeLevel')->findOrFail($studentId);
        $minOrdinal = (int) $this->settings->get('portal.learner_min_grade_ordinal', new ScopeChain(schoolId: $student->school_id));

        if ($student->gradeLevel === null) {
            return false;
        }

        return $student->gradeLevel->ordinal >= $minOrdinal;
    }
}
