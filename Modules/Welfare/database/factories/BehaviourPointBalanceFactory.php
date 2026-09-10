<?php

declare(strict_types=1);

namespace Modules\Welfare\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Core\Models\AcademicYear;
use Modules\Core\Models\School;
use Modules\Core\Models\Term;
use Modules\People\Models\Student;
use Modules\Welfare\Models\BehaviourPointBalance;

/**
 * @extends Factory<BehaviourPointBalance>
 */
class BehaviourPointBalanceFactory extends Factory
{
    protected $model = BehaviourPointBalance::class;

    public function definition(): array
    {
        $school = School::factory();
        $year = AcademicYear::factory()->for($school);

        return [
            'school_id' => $school,
            'student_id' => Student::factory()->for($school),
            'term_id' => Term::factory()->for($school)->for($year, 'academicYear'),
            'merit_points' => 0,
            'demerit_points' => 0,
            'net_points' => 0,
            'record_count' => 0,
        ];
    }
}
