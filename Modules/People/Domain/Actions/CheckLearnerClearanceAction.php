<?php

declare(strict_types=1);

namespace Modules\People\Domain\Actions;

use Modules\Core\Domain\Actions\Action;
use Modules\Core\Domain\Registry\LearnerClearanceRegistry;
use Modules\People\Models\Student;

/**
 * ACT-CheckLearnerClearance (Book C PPL-01 §7/BR-PPL-01-014). Runs every
 * clearance check the modules have registered (library, boarding property,
 * fees…) and lists each failure by module. Read only.
 */
final class CheckLearnerClearanceAction extends Action
{
    protected bool $transactional = false;

    /**
     * @return array<string, array<int, string>> module key => reasons the learner is not clear (empty array when clear)
     */
    public function execute(int $studentId): array
    {
        $student = Student::findOrFail($studentId);
        $results = [];

        foreach (LearnerClearanceRegistry::all() as $key => $check) {
            $results[$key] = $check($student->school_id, $student->id);
        }

        return $results;
    }
}
