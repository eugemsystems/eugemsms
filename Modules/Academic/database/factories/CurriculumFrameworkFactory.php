<?php

declare(strict_types=1);

namespace Modules\Academic\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Academic\Models\CurriculumFramework;
use Modules\Core\Models\School;

/**
 * @extends Factory<CurriculumFramework>
 */
class CurriculumFrameworkFactory extends Factory
{
    protected $model = CurriculumFramework::class;

    public function definition(): array
    {
        return [
            'school_id' => School::factory(),
            'code' => 'HBC_2024',
            'name' => 'Heritage-Based Curriculum 2024-2030',
            'authority' => 'MoPSE',
            'effective_from' => '2024-05-01',
            'effective_to' => null,
            'status' => 'active',
            'continuous_assessment_model' => 'sbp',
            'reference_circular' => 'Circular No. 9 of 2024',
        ];
    }
}
