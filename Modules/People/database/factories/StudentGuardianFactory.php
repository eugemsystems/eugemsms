<?php

declare(strict_types=1);

namespace Modules\People\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Core\Models\School;
use Modules\People\Models\Guardian;
use Modules\People\Models\Student;
use Modules\People\Models\StudentGuardian;

/**
 * @extends Factory<StudentGuardian>
 */
class StudentGuardianFactory extends Factory
{
    protected $model = StudentGuardian::class;

    public function definition(): array
    {
        $school = School::factory();

        return [
            'school_id' => $school,
            'student_id' => Student::factory()->for($school),
            'guardian_id' => Guardian::factory()->for($school),
            'relationship' => 'father',
            'is_primary_contact' => true,
            'is_emergency_contact' => true,
            'is_fee_responsible' => true,
            'status' => 'active',
            'effective_from' => now()->toDateString(),
        ];
    }

    public function feeResponsible(): self
    {
        return $this->state(fn (): array => ['is_fee_responsible' => true]);
    }

    public function courtRestricted(): self
    {
        return $this->state(fn (): array => ['has_court_restriction' => true, 'may_collect_learner' => true]);
    }
}
