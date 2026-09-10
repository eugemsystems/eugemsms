<?php

declare(strict_types=1);

namespace Modules\Payroll\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Core\Models\School;
use Modules\Payroll\Models\PayComponent;
use Modules\Payroll\Models\StaffPayComponent;
use Modules\Payroll\Models\StaffPayStructure;

/**
 * @extends Factory<StaffPayComponent>
 */
class StaffPayComponentFactory extends Factory
{
    protected $model = StaffPayComponent::class;

    public function definition(): array
    {
        $school = School::factory();

        return [
            'school_id' => $school,
            'pay_structure_id' => StaffPayStructure::factory()->for($school),
            'component_id' => PayComponent::factory()->for($school),
            'amount_minor' => 50000,
            'currency' => 'USD',
            'effective_from' => now()->subMonths(6)->toDateString(),
        ];
    }
}
