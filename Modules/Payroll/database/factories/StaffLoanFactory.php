<?php

declare(strict_types=1);

namespace Modules\Payroll\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Core\Models\School;
use Modules\Payroll\Models\StaffLoan;
use Modules\People\Models\Staff;

/**
 * @extends Factory<StaffLoan>
 */
class StaffLoanFactory extends Factory
{
    protected $model = StaffLoan::class;

    public function definition(): array
    {
        $school = School::factory();

        return [
            'school_id' => $school,
            'staff_id' => Staff::factory()->for($school),
            'loan_type' => 'staff_loan',
            'principal_minor' => 100000,
            'currency' => 'USD',
            'instalment_minor' => 10000,
            'instalment_count' => 10,
            'starts_on' => now()->toDateString(),
            'outstanding_minor' => 100000,
            'paid_minor' => 0,
            'status' => 'active',
        ];
    }
}
