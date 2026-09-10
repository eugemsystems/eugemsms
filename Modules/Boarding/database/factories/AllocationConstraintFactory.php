<?php

declare(strict_types=1);

namespace Modules\Boarding\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Boarding\Models\AllocationConstraint;
use Modules\Core\Models\School;

/**
 * @extends Factory<AllocationConstraint>
 */
class AllocationConstraintFactory extends Factory
{
    protected $model = AllocationConstraint::class;

    public function definition(): array
    {
        return [
            'school_id' => School::factory(),
            'constraint_type' => 'level_band',
            'severity' => 'soft',
            'weight' => 1,
            'value' => 2,
            'is_active' => true,
        ];
    }
}
