<?php

declare(strict_types=1);

namespace Modules\Academic\Domain\Actions;

use Modules\Academic\Domain\DataObjects\CreateProjectRubricData;
use Modules\Academic\Domain\Exceptions\RubricWeightMismatchException;
use Modules\Academic\Models\ProjectRubric;
use Modules\Academic\Models\RubricCriterion;
use Modules\Core\Domain\Actions\Action;

/**
 * ACT-CreateProjectRubric (Book E ACA-06 §2/§6/BR-ACA-06-007). Takes
 * the rubric and its complete set of criteria together, mirroring
 * `CreateGradingScaleAction`'s own reasoning: a partial set of
 * criteria can never be checked against the 100% total, so there is
 * no sound way to validate the weight sum incrementally.
 */
final class CreateProjectRubricAction extends Action
{
    public function execute(CreateProjectRubricData $data): ProjectRubric
    {
        $totalWeight = round(array_sum(array_map(fn ($c) => $c->weightPercent, $data->criteria)), 2);

        if (abs($totalWeight - 100.0) > 0.01) {
            throw RubricWeightMismatchException::forTotal($totalWeight);
        }

        return $this->transaction(function () use ($data): ProjectRubric {
            $rubric = ProjectRubric::create([
                'school_id' => $data->schoolId,
                'name' => $data->name,
                'subject_id' => $data->subjectId,
                'total_mark' => $data->totalMark,
                'is_template' => $data->isTemplate,
                'is_active' => $data->isActive,
            ]);

            foreach ($data->criteria as $index => $criterion) {
                RubricCriterion::create([
                    'rubric_id' => $rubric->id,
                    'criterion' => $criterion->criterion,
                    'description' => $criterion->description,
                    'max_mark' => $criterion->maxMark,
                    'weight_percent' => $criterion->weightPercent,
                    'performance_levels' => $criterion->performanceLevels,
                    'sort_order' => $index + 1,
                ]);
            }

            return $rubric;
        });
    }
}
