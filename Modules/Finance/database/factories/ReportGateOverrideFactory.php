<?php

declare(strict_types=1);

namespace Modules\Finance\Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Core\Models\AcademicYear;
use Modules\Core\Models\School;
use Modules\Core\Models\Term;
use Modules\Finance\Models\ReportGateOverride;
use Modules\People\Models\Student;

/**
 * @extends Factory<ReportGateOverride>
 */
class ReportGateOverrideFactory extends Factory
{
    protected $model = ReportGateOverride::class;

    public function definition(): array
    {
        $school = School::factory();
        $year = AcademicYear::factory()->for($school);

        return [
            'school_id' => $school,
            'student_id' => Student::factory()->for($school),
            'term_id' => Term::factory()->for($school)->for($year, 'academicYear'),
            'reason' => fake()->sentence(8),
            'granted_by' => User::factory(),
            'created_at' => now(),
        ];
    }
}
