<?php

declare(strict_types=1);

namespace Modules\Payroll\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Core\Models\School;
use Modules\Payroll\Models\PayGrade;
use Modules\Payroll\Models\PayGradeNotch;

/**
 * @extends Factory<PayGradeNotch>
 */
class PayGradeNotchFactory extends Factory
{
    protected $model = PayGradeNotch::class;

    public function definition(): array
    {
        $school = School::factory();

        return [
            'school_id' => $school,
            'grade_id' => PayGrade::factory()->for($school),
            'notch' => 'N1',
            'basic_salary_minor' => 50000,
            'currency' => 'USD',
            'effective_from' => now()->subYear()->toDateString(),
        ];
    }
}
