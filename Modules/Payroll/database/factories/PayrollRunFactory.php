<?php

declare(strict_types=1);

namespace Modules\Payroll\Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Core\Models\AcademicYear;
use Modules\Core\Models\School;
use Modules\Core\Models\Term;
use Modules\Payroll\Models\PayrollRun;

/**
 * @extends Factory<PayrollRun>
 */
class PayrollRunFactory extends Factory
{
    protected $model = PayrollRun::class;

    public function definition(): array
    {
        $school = School::factory();
        $year = AcademicYear::factory()->for($school);

        return [
            'school_id' => $school,
            'academic_year_id' => $year,
            'term_id' => Term::factory()->for($school)->for($year, 'academicYear'),
            'period_month' => now()->format('Y-m'),
            'run_number' => 'PR-'.fake()->unique()->numerify('######'),
            'run_type' => 'regular',
            'pay_date' => now()->toDateString(),
            'period_start' => now()->startOfMonth()->toDateString(),
            'period_end' => now()->endOfMonth()->toDateString(),
            'status' => 'draft',
            'computed_by' => User::factory(),
        ];
    }
}
