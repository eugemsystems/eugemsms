<?php

declare(strict_types=1);

namespace Modules\Welfare\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Core\Models\School;
use Modules\People\Models\Student;
use Modules\Welfare\Models\MedicalCondition;

/**
 * @extends Factory<MedicalCondition>
 */
class MedicalConditionFactory extends Factory
{
    protected $model = MedicalCondition::class;

    public function definition(): array
    {
        $school = School::factory();

        return [
            'school_id' => $school,
            'student_id' => Student::factory()->for($school),
            'condition_type' => 'allergy',
            'category' => 'anaphylaxis',
            'name' => 'Severe peanut allergy',
            'severity' => 'severe',
            'public_summary' => 'Severe nut allergy — EpiPen',
            'requires_emergency_plan' => true,
            'affects_dietary' => true,
            'status' => 'active',
            'declared_by' => 'guardian',
            'effective_from' => now()->toDateString(),
        ];
    }
}
