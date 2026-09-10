<?php

declare(strict_types=1);

namespace Modules\Compliance\Domain\Actions;

use Modules\Core\Domain\Actions\Action;
use Modules\Core\Models\GradeLevel;
use Modules\People\Models\Student;

/**
 * ACT-BuildEnrolmentSnapshot (Book H3 CMP-02 §3/BR-CMP-02-003). Every
 * active learner, broken down by level, gender and residency as the
 * census requires — a plain live query, snapshotted by whoever calls
 * this (`GenerateStatutorySchoolReturnAction`), never cached here.
 */
final class BuildEnrolmentSnapshotAction extends Action
{
    /**
     * @return array{total: int, by_level: array<int, array{grade_level_id: int, grade_level_name: string, count: int}>, by_gender: array<string, int>, by_residency: array<string, int>}
     */
    public function execute(int $schoolId): array
    {
        $students = Student::where('school_id', $schoolId)->where('status', 'active')->get();
        $gradeLevels = GradeLevel::withoutGlobalScopes()->whereIn('id', $students->pluck('grade_level_id')->unique())->get()->keyBy('id');

        return [
            'total' => $students->count(),
            'by_level' => $students->groupBy('grade_level_id')->map(function ($rows, $gradeLevelId) use ($gradeLevels): array {
                $level = $gradeLevels->get((int) $gradeLevelId);

                return [
                    'grade_level_id' => (int) $gradeLevelId,
                    'grade_level_name' => $level !== null ? $level->name : 'Unknown',
                    'count' => $rows->count(),
                ];
            })->values()->all(),
            'by_gender' => $students->groupBy('gender')->map(fn ($rows) => $rows->count())->all(),
            'by_residency' => $students->groupBy('residency')->map(fn ($rows) => $rows->count())->all(),
        ];
    }
}
