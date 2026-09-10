<?php

declare(strict_types=1);

namespace Modules\Academic\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Academic\Models\GradeBand;
use Modules\Academic\Models\GradingScale;
use Modules\Core\Models\School;

/**
 * @extends Factory<GradeBand>
 */
class GradeBandFactory extends Factory
{
    protected $model = GradeBand::class;

    public function definition(): array
    {
        $school = School::factory();

        return [
            'school_id' => $school,
            'grading_scale_id' => GradingScale::factory()->for($school),
            'grade' => 'A',
            'min_percent' => 80,
            'max_percent' => 100,
            'is_pass' => true,
            'sort_order' => 1,
        ];
    }
}
