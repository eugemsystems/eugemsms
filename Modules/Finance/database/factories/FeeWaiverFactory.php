<?php

declare(strict_types=1);

namespace Modules\Finance\Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Core\Models\AcademicYear;
use Modules\Core\Models\School;
use Modules\Core\Models\Term;
use Modules\Finance\Models\FeeWaiver;
use Modules\People\Models\Student;

/**
 * @extends Factory<FeeWaiver>
 */
class FeeWaiverFactory extends Factory
{
    protected $model = FeeWaiver::class;

    public function definition(): array
    {
        $school = School::factory();
        $year = AcademicYear::factory()->for($school);

        return [
            'school_id' => $school,
            'term_id' => Term::factory()->for($school)->for($year, 'academicYear'),
            'student_id' => Student::factory()->for($school),
            'type' => 'waiver',
            'amount_minor' => 5000,
            'currency' => 'USD',
            'reason_code' => 'hardship',
            'reason' => fake()->sentence(8),
            'status' => 'pending',
            'requested_by' => User::factory(),
            'created_at' => now(),
        ];
    }
}
