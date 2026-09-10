<?php

declare(strict_types=1);

namespace Modules\Payroll\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Core\Models\School;
use Modules\Payroll\Models\PayComponent;

/**
 * @extends Factory<PayComponent>
 */
class PayComponentFactory extends Factory
{
    protected $model = PayComponent::class;

    public function definition(): array
    {
        return [
            'school_id' => School::factory(),
            'code' => 'BASIC',
            'name' => 'Basic Salary',
            'component_type' => 'earning',
            'category' => 'basic',
            'calculation_method' => 'fixed',
            'is_taxable' => true,
            'is_pensionable' => true,
            'is_nec_applicable' => true,
            'is_zimdef_applicable' => true,
            'taxable_percent' => 100,
            'cost_centre_source' => 'staff',
            'appears_on_payslip' => true,
            'is_active' => true,
        ];
    }

    public function allowance(): self
    {
        return $this->state(fn (): array => [
            'code' => 'ALLOW_'.fake()->unique()->numerify('###'),
            'name' => 'Allowance',
            'category' => 'allowance',
        ]);
    }

    public function statutoryDeduction(): self
    {
        return $this->state(fn (): array => [
            'component_type' => 'deduction',
            'category' => 'statutory',
            'is_taxable' => false,
        ]);
    }
}
