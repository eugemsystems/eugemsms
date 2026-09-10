<?php

declare(strict_types=1);

namespace Modules\Welfare\Domain\Actions;

use Illuminate\Support\Carbon;
use Modules\Core\Domain\Actions\Action;
use Modules\Core\Domain\Support\Settings\ScopeChain;
use Modules\Core\Domain\Support\Settings\SettingResolver;
use Modules\Welfare\Domain\Events\ConductGradeComputed;
use Modules\Welfare\Models\BehaviourPointBalance;
use Modules\Welfare\Models\BehaviourRecord;

/**
 * ACT-RebuildBehaviourPointBalance (Book G BRD-07 §2/BR-BRD-07-002/015).
 * Rebuilt from `behaviour_records` — never a running counter incremented
 * in place, so a correction to a record is reflected on the next
 * rebuild rather than drifting silently. Real and directly testable
 * against a fixed term; the "nightly and at term close" cadence itself
 * is a deployment/scheduler concern, matching this codebase's own
 * established boundary for every other rebuild-style action.
 */
final class RebuildBehaviourPointBalanceAction extends Action
{
    public function __construct(
        private readonly SettingResolver $settings,
    ) {}

    public function execute(int $schoolId, int $studentId, int $termId): BehaviourPointBalance
    {
        $records = BehaviourRecord::query()
            ->where('school_id', $schoolId)
            ->where('student_id', $studentId)
            ->where('term_id', $termId)
            ->get();

        $meritPoints = (int) $records->where('polarity', 'positive')->sum('points');
        $demeritPoints = (int) abs($records->where('polarity', 'negative')->sum('points'));
        $netPoints = (int) $records->sum('points');

        $conductGrade = $this->conductGradeFor($schoolId, $netPoints);

        return $this->transaction(function () use ($schoolId, $studentId, $termId, $meritPoints, $demeritPoints, $netPoints, $records, $conductGrade): BehaviourPointBalance {
            $balance = BehaviourPointBalance::updateOrCreate(
                ['school_id' => $schoolId, 'student_id' => $studentId, 'term_id' => $termId],
                [
                    'merit_points' => $meritPoints,
                    'demerit_points' => $demeritPoints,
                    'net_points' => $netPoints,
                    'record_count' => $records->count(),
                    'conduct_grade' => $conductGrade,
                    'rebuilt_at' => Carbon::now(),
                ],
            );

            event(new ConductGradeComputed($balance));

            return $balance;
        });
    }

    /**
     * BR-BRD-07-015 — bands are configurable per school
     * (`behaviour.conduct_grade_bands`, seeded), each shaped
     * `{"min": int, "grade": string}`, evaluated highest `min` first.
     */
    private function conductGradeFor(int $schoolId, int $netPoints): ?string
    {
        $scope = new ScopeChain(schoolId: $schoolId);
        $raw = $this->settings->get('behaviour.conduct_grade_bands', $scope);
        $bands = is_array($raw) ? $raw : json_decode((string) $raw, true);

        if (! is_array($bands) || $bands === []) {
            return null;
        }

        $sorted = collect($bands)->sortByDesc('min')->values();

        foreach ($sorted as $band) {
            if ($netPoints >= (int) $band['min']) {
                return (string) $band['grade'];
            }
        }

        return null;
    }
}
