<?php

declare(strict_types=1);

namespace Modules\Academic\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Academic\Models\ObservationRubric;
use Modules\Core\Models\School;

/**
 * @extends Factory<ObservationRubric>
 */
class ObservationRubricFactory extends Factory
{
    protected $model = ObservationRubric::class;

    public function definition(): array
    {
        return [
            'school_id' => School::factory(),
            'name' => 'Standard Lesson Observation Rubric',
            'criteria' => [
                ['criterion' => 'Lesson planning', 'descriptor_levels' => ['emerging', 'developing', 'proficient', 'exemplary']],
                ['criterion' => 'Learner engagement', 'descriptor_levels' => ['emerging', 'developing', 'proficient', 'exemplary']],
            ],
        ];
    }
}
