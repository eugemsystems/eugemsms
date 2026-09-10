<?php

declare(strict_types=1);

namespace Modules\Welfare\Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Core\Models\School;
use Modules\People\Models\Staff;
use Modules\People\Models\Student;
use Modules\Welfare\Models\SafeguardingCase;

/**
 * @extends Factory<SafeguardingCase>
 */
class SafeguardingCaseFactory extends Factory
{
    protected $model = SafeguardingCase::class;

    public function definition(): array
    {
        $school = School::factory();

        return [
            'school_id' => $school,
            'case_reference' => 'SG/'.fake()->unique()->numberBetween(1000, 99999),
            'student_id' => Student::factory()->for($school),
            'opened_at' => now(),
            'opened_by' => User::factory(),
            'lead_staff_id' => Staff::factory()->for($school),
            'category' => 'emotional',
            'risk_level' => 'medium',
            'summary' => 'Initial concern under assessment.',
            'status' => 'open',
            'external_agency_involved' => false,
        ];
    }
}
