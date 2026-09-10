<?php

declare(strict_types=1);

namespace Modules\Compliance\Domain\Actions;

use Modules\Compliance\Domain\Registry\PersonalDataTableRegistry;
use Modules\Compliance\Models\RetentionSchedule;
use Modules\Core\Domain\Actions\Action;

/**
 * ACT-CheckRetentionScheduleCoverage (Book H3 CMP-03 §3 ⭐/BR-CMP-03-
 * 005 (AC-CMP-03-006)). The nightly registry check: every table
 * `PersonalDataTableRegistry` knows about must appear in at least one
 * ACTIVE `retention_schedules` row's `table_names` for the school —
 * a table covered by no schedule fails this check and is reported,
 * never silently passed.
 */
final class CheckRetentionScheduleCoverageAction extends Action
{
    /**
     * @return array{covered: array<int, string>, uncovered: array<int, string>}
     */
    public function execute(int $schoolId): array
    {
        $coveredTables = RetentionSchedule::where('school_id', $schoolId)
            ->where('is_active', true)
            ->get()
            ->flatMap(fn (RetentionSchedule $schedule): array => $schedule->table_names)
            ->unique()
            ->all();

        $registered = array_keys(PersonalDataTableRegistry::all());

        return [
            'covered' => array_values(array_intersect($registered, $coveredTables)),
            'uncovered' => array_values(array_diff($registered, $coveredTables)),
        ];
    }
}
