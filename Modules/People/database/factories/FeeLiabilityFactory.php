<?php

declare(strict_types=1);

namespace Modules\People\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Core\Models\School;
use Modules\People\Models\FeeLiability;
use Modules\People\Models\Guardian;
use Modules\People\Models\Student;

/**
 * @extends Factory<FeeLiability>
 */
class FeeLiabilityFactory extends Factory
{
    protected $model = FeeLiability::class;

    public function definition(): array
    {
        $school = School::factory();

        return [
            'school_id' => $school,
            'student_id' => Student::factory()->for($school),
            'guardian_id' => Guardian::factory()->for($school),
            'component_id' => null,
            'share_type' => 'percentage',
            'share_percent' => 100,
            'priority' => 100,
            'effective_from' => now()->subYear()->toDateString(),
            'is_active' => true,
        ];
    }
}
