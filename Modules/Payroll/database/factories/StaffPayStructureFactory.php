<?php

declare(strict_types=1);

namespace Modules\Payroll\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Core\Models\School;
use Modules\Payroll\Models\StaffPayStructure;
use Modules\People\Models\Staff;

/**
 * @extends Factory<StaffPayStructure>
 */
class StaffPayStructureFactory extends Factory
{
    protected $model = StaffPayStructure::class;

    public function definition(): array
    {
        $school = School::factory();

        return [
            'school_id' => $school,
            'staff_id' => Staff::factory()->for($school),
            'primary_currency' => 'USD',
            'payment_currency' => 'USD',
            'effective_from' => now()->subMonths(6)->toDateString(),
            'status' => 'active',
        ];
    }
}
