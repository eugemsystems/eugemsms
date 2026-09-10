<?php

declare(strict_types=1);

namespace Modules\Academic\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Academic\Models\ProjectRubric;
use Modules\Academic\Models\RubricCriterion;

/**
 * @extends Factory<RubricCriterion>
 */
class RubricCriterionFactory extends Factory
{
    protected $model = RubricCriterion::class;

    public function definition(): array
    {
        return [
            'rubric_id' => ProjectRubric::factory(),
            'criterion' => 'Research & Planning',
            'description' => 'Depth and relevance of research underpinning the project.',
            'max_mark' => '25.00',
            'weight_percent' => '25.00',
            'performance_levels' => [
                ['level' => 'Outstanding', 'min_mark' => 20, 'descriptor' => 'Exceptional depth and relevance'],
                ['level' => 'Satisfactory', 'min_mark' => 10, 'descriptor' => 'Adequate research'],
                ['level' => 'Needs Improvement', 'min_mark' => 0, 'descriptor' => 'Limited or shallow research'],
            ],
            'sort_order' => 1,
        ];
    }
}
