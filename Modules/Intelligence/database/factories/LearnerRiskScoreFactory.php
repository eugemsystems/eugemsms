<?php

declare(strict_types=1);

namespace Modules\Intelligence\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Core\Models\School;
use Modules\Core\Models\Term;
use Modules\Intelligence\Models\LearnerRiskScore;
use Modules\People\Models\Student;

/**
 * @extends Factory<LearnerRiskScore>
 */
class LearnerRiskScoreFactory extends Factory
{
    protected $model = LearnerRiskScore::class;

    public function definition(): array
    {
        $school = School::factory();

        return [
            'school_id' => $school,
            'student_id' => Student::factory()->for($school),
            'term_id' => Term::factory()->for($school),
            'composite_score' => 20,
            'risk_band' => 'low',
            'contributing_factors' => [],
            'computed_at' => now(),
        ];
    }
}
