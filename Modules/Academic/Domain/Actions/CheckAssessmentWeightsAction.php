<?php

declare(strict_types=1);

namespace Modules\Academic\Domain\Actions;

use Modules\Academic\Models\Assessment;
use Modules\Academic\Models\Subject;
use Modules\Core\Domain\Actions\Action;

/**
 * ACT-CheckAssessmentWeights (Book D ACA-05 §5/BR-ACA-05-004/AC-ACA-05-001).
 * Reports every subject whose assessment weights for the term do not total
 * 100%. Only subjects that have assessments are checked — a subject nobody
 * assessed this term has no weights to be wrong.
 */
final class CheckAssessmentWeightsAction extends Action
{
    protected bool $transactional = false;

    /**
     * @param  array<int, int>|null  $subjectIds  limit the check to these subjects
     * @return array<int, array{subject_id: int, subject: string, total_percent: float, difference: float}>
     */
    public function execute(int $termId, ?array $subjectIds = null): array
    {
        $totals = Assessment::query()
            ->where('term_id', $termId)
            ->when($subjectIds !== null, fn ($q) => $q->whereIn('subject_id', $subjectIds))
            ->get(['subject_id', 'grade_level_id', 'class_id', 'teaching_group_id', 'weight_percent'])
            ->groupBy(fn (Assessment $a): string => implode(':', [$a->subject_id, $a->grade_level_id ?? '-', $a->class_id ?? '-', $a->teaching_group_id ?? '-']))
            ->map(fn ($group): array => ['subject_id' => (int) $group->first()->subject_id, 'total' => round((float) $group->sum('weight_percent'), 2), 'scope' => $group->first()]);

        $names = Subject::query()->whereIn('id', $totals->pluck('subject_id'))->pluck('name', 'id');
        $problems = [];

        foreach ($totals as $group) {
            if (abs($group['total'] - 100.0) > 0.01) {
                $scope = $group['scope'];
                $label = (string) ($names[$group['subject_id']] ?? "#{$group['subject_id']}");
                $suffix = $scope->class_id !== null ? " (class #{$scope->class_id})" : ($scope->teaching_group_id !== null ? " (group #{$scope->teaching_group_id})" : ($scope->grade_level_id !== null ? " (level #{$scope->grade_level_id})" : ''));

                $problems[] = [
                    'subject_id' => $group['subject_id'],
                    'subject' => $label.$suffix,
                    'total_percent' => $group['total'],
                    'difference' => round($group['total'] - 100.0, 2),
                ];
            }
        }

        return $problems;
    }
}
