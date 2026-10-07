<?php

declare(strict_types=1);

namespace Modules\People\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Core\Models\School;
use Modules\People\Models\StaffAppraisalRubric;

/**
 * @extends Factory<StaffAppraisalRubric>
 */
class StaffAppraisalRubricFactory extends Factory
{
    protected $model = StaffAppraisalRubric::class;

    public function definition(): array
    {
        return [
            'school_id' => School::factory(),
            'name' => 'Standard Appraisal Rubric',
            'criteria' => [
                ['criterion' => 'Job knowledge', 'descriptor_levels' => ['unsatisfactory', 'developing', 'proficient', 'outstanding']],
                ['criterion' => 'Collaboration', 'descriptor_levels' => ['unsatisfactory', 'developing', 'proficient', 'outstanding']],
            ],
        ];
    }
}
