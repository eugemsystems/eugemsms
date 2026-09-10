<?php

declare(strict_types=1);

namespace Modules\Boarding\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Boarding\Models\DietaryRequirement;
use Modules\Core\Models\School;
use Modules\People\Models\Student;

/**
 * @extends Factory<DietaryRequirement>
 */
class DietaryRequirementFactory extends Factory
{
    protected $model = DietaryRequirement::class;

    public function definition(): array
    {
        $school = School::factory();

        return [
            'school_id' => $school,
            'student_id' => Student::factory()->for($school),
            'requirement_type' => 'allergy',
            'severity' => 'severe',
            'allergens' => ['nuts'],
            'description' => 'Severe nut allergy.',
            'requires_epipen' => false,
            'verified_by_nurse' => false,
            'effective_from' => now()->toDateString(),
            'is_active' => true,
        ];
    }
}
