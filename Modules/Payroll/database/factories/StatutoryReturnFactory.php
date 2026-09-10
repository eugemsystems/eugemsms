<?php

declare(strict_types=1);

namespace Modules\Payroll\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Core\Models\School;
use Modules\Payroll\Models\StatutoryReturn;

/**
 * @extends Factory<StatutoryReturn>
 */
class StatutoryReturnFactory extends Factory
{
    protected $model = StatutoryReturn::class;

    public function definition(): array
    {
        return [
            'school_id' => School::factory(),
            'return_type' => 'p2_paye',
            'period_type' => 'monthly',
            'period_reference' => now()->format('Y-m'),
            'due_date' => now()->addMonth()->startOfMonth()->addDays(9)->toDateString(),
            'amount_due_minor' => 0,
            'currency' => 'USD',
            'supporting_data' => [],
            'status' => 'pending',
        ];
    }
}
