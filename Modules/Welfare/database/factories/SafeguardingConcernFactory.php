<?php

declare(strict_types=1);

namespace Modules\Welfare\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Core\Models\School;
use Modules\People\Models\Student;
use Modules\Welfare\Models\SafeguardingConcern;

/**
 * @extends Factory<SafeguardingConcern>
 */
class SafeguardingConcernFactory extends Factory
{
    protected $model = SafeguardingConcern::class;

    public function definition(): array
    {
        $school = School::factory();

        return [
            'school_id' => $school,
            'student_id' => Student::factory()->for($school),
            'reported_at' => now(),
            'report_source' => 'staff',
            'concern_category' => 'emotional',
            'description' => 'A staff member noticed a change in behaviour.',
            'immediate_risk' => false,
            'triage_status' => 'awaiting_triage',
        ];
    }
}
