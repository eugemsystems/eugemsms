<?php

declare(strict_types=1);

namespace Modules\Intelligence\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Core\Models\School;
use Modules\Intelligence\Models\FeeDefaultRiskScore;
use Modules\People\Models\Guardian;
use Modules\People\Models\Student;

/**
 * @extends Factory<FeeDefaultRiskScore>
 */
class FeeDefaultRiskScoreFactory extends Factory
{
    protected $model = FeeDefaultRiskScore::class;

    public function definition(): array
    {
        $school = School::factory();

        return [
            'school_id' => $school,
            'student_id' => Student::factory()->for($school),
            'guardian_id' => Guardian::factory()->for($school),
            'risk_score' => 20,
            'contributing_factors' => [],
            'recommended_action' => null,
            'computed_at' => now(),
        ];
    }
}
