<?php

declare(strict_types=1);

namespace Modules\People\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Core\Models\School;
use Modules\People\Models\LeaveType;

/**
 * @extends Factory<LeaveType>
 */
class LeaveTypeFactory extends Factory
{
    protected $model = LeaveType::class;

    public function definition(): array
    {
        return [
            'school_id' => School::factory(),
            'code' => fake()->unique()->lexify('LV??'),
            'name' => 'Annual Leave',
            'annual_entitlement_days' => 21,
            'accrual_method' => 'annual',
            'is_paid' => true,
            'requires_document' => false,
            'requires_cover' => true,
            'is_active' => true,
        ];
    }
}
