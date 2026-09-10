<?php

declare(strict_types=1);

namespace Modules\Academic\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Academic\Models\AssessmentType;
use Modules\Core\Models\School;

/**
 * @extends Factory<AssessmentType>
 */
class AssessmentTypeFactory extends Factory
{
    protected $model = AssessmentType::class;

    public function definition(): array
    {
        return [
            'school_id' => School::factory(),
            'code' => strtoupper($this->faker->unique()->lexify('TYPE????')),
            'name' => 'Topic Test',
            'category' => 'coursework',
            'default_weight_percent' => 10,
            'appears_on_report_card' => true,
            'is_examination' => false,
        ];
    }
}
