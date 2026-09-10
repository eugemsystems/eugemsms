<?php

declare(strict_types=1);

namespace Modules\Intelligence\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Core\Models\School;
use Modules\Intelligence\Models\WithdrawalRiskFlag;
use Modules\People\Models\Student;

/**
 * @extends Factory<WithdrawalRiskFlag>
 */
class WithdrawalRiskFlagFactory extends Factory
{
    protected $model = WithdrawalRiskFlag::class;

    public function definition(): array
    {
        $school = School::factory();

        return [
            'school_id' => $school,
            'student_id' => Student::factory()->for($school),
            'flagged_at' => now(),
            'contributing_factors' => [],
            'status' => 'open',
            'reviewed_by' => null,
            'intervention_note' => null,
        ];
    }
}
