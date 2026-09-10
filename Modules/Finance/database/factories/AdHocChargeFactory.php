<?php

declare(strict_types=1);

namespace Modules\Finance\Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Core\Models\AcademicYear;
use Modules\Core\Models\School;
use Modules\Core\Models\Term;
use Modules\Finance\Models\AdHocCharge;
use Modules\Finance\Models\FeeComponent;
use Modules\People\Models\Student;

/**
 * @extends Factory<AdHocCharge>
 */
class AdHocChargeFactory extends Factory
{
    protected $model = AdHocCharge::class;

    public function definition(): array
    {
        $school = School::factory();
        $year = AcademicYear::factory()->for($school);

        return [
            'school_id' => $school,
            'academic_year_id' => $year,
            'term_id' => Term::factory()->for($school)->for($year, 'academicYear'),
            'student_id' => Student::factory()->for($school),
            'component_id' => FeeComponent::factory()->for($school),
            'description' => fake()->sentence(4),
            'quantity' => 1,
            'unit_rate_minor' => 2000,
            'amount_minor' => 2000,
            'currency' => 'USD',
            'status' => 'pending',
            'raised_by' => User::factory(),
            'created_at' => now(),
        ];
    }
}
