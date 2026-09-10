<?php

declare(strict_types=1);

namespace Modules\Welfare\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Core\Models\School;
use Modules\People\Models\Staff;
use Modules\People\Models\Student;
use Modules\Welfare\Models\CounsellingSession;

/**
 * @extends Factory<CounsellingSession>
 */
class CounsellingSessionFactory extends Factory
{
    protected $model = CounsellingSession::class;

    public function definition(): array
    {
        $school = School::factory();

        return [
            'school_id' => $school,
            'student_id' => Student::factory()->for($school),
            'counsellor_staff_id' => Staff::factory()->for($school),
            'session_at' => now(),
            'session_type' => 'individual',
            'risk_indicators_present' => false,
            'attended' => true,
        ];
    }
}
