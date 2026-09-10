<?php

declare(strict_types=1);

namespace Modules\Payroll\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Core\Models\School;
use Modules\Payroll\Models\Payslip;
use Modules\Payroll\Models\PayslipLine;

/**
 * @extends Factory<PayslipLine>
 */
class PayslipLineFactory extends Factory
{
    protected $model = PayslipLine::class;

    public function definition(): array
    {
        $school = School::factory();

        return [
            'school_id' => $school,
            'payslip_id' => Payslip::factory()->for($school),
            'component_type' => 'earning',
            'description' => 'Basic Salary',
            'amount_minor' => 50000,
            'currency' => 'USD',
            'is_taxable' => true,
        ];
    }
}
