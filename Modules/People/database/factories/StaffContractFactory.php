<?php

declare(strict_types=1);

namespace Modules\People\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Core\Models\School;
use Modules\People\Models\Staff;
use Modules\People\Models\StaffContract;

/**
 * @extends Factory<StaffContract>
 */
class StaffContractFactory extends Factory
{
    protected $model = StaffContract::class;

    public function definition(): array
    {
        return [
            'school_id' => School::factory(),
            'staff_id' => Staff::factory(),
            'contract_type' => 'permanent',
            'starts_on' => now()->toDateString(),
            'notice_period_days' => 30,
            'status' => 'active',
            'created_at' => now(),
        ];
    }
}
