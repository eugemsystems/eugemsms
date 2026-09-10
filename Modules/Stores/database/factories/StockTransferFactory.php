<?php

declare(strict_types=1);

namespace Modules\Stores\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Core\Models\School;
use Modules\Stores\Models\StockTransfer;
use Modules\Stores\Models\Store;

/**
 * @extends Factory<StockTransfer>
 */
class StockTransferFactory extends Factory
{
    protected $model = StockTransfer::class;

    public function definition(): array
    {
        $school = School::factory();

        return [
            'school_id' => $school,
            'transfer_number' => 'TRF/'.fake()->unique()->numberBetween(1000, 99999),
            'from_store_id' => Store::factory()->for($school),
            'to_store_id' => Store::factory()->for($school)->kitchen(),
            'reason' => 'Weekly kitchen resupply.',
            'status' => 'draft',
        ];
    }
}
