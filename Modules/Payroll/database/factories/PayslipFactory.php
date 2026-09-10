<?php

declare(strict_types=1);

namespace Modules\Payroll\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Core\Models\School;
use Modules\Payroll\Models\PayrollRun;
use Modules\Payroll\Models\Payslip;
use Modules\People\Models\Staff;

/**
 * @extends Factory<Payslip>
 */
class PayslipFactory extends Factory
{
    protected $model = Payslip::class;

    public function definition(): array
    {
        $school = School::factory();

        return [
            'school_id' => $school,
            'payroll_run_id' => PayrollRun::factory()->for($school),
            'staff_id' => Staff::factory()->for($school),
            'payslip_number' => 'PS-'.fake()->unique()->numerify('######'),
            'basic_minor' => 50000,
            'gross_minor' => 50000,
            'taxable_gross_minor' => 50000,
            'pensionable_gross_minor' => 50000,
            'total_deductions_minor' => 0,
            'net_pay_minor' => 50000,
            'employer_cost_minor' => 50000,
            'currency' => 'USD',
            'ytd_gross_minor' => 50000,
            'ytd_paye_minor' => 0,
            'ytd_nssa_minor' => 0,
        ];
    }
}
