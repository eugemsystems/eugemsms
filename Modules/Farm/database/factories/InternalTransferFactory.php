<?php

declare(strict_types=1);

namespace Modules\Farm\Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Core\Models\AcademicYear;
use Modules\Core\Models\School;
use Modules\Core\Models\Term;
use Modules\Farm\Models\InternalTransfer;
use Modules\Farm\Models\ProductionUnit;
use Modules\Stores\Models\InventoryItem;
use Modules\Stores\Models\Store;

/**
 * @extends Factory<InternalTransfer>
 */
class InternalTransferFactory extends Factory
{
    protected $model = InternalTransfer::class;

    public function definition(): array
    {
        return [
            'school_id' => School::factory(),
            'term_id' => fn (array $attributes): int => Term::factory()->create([
                'school_id' => $attributes['school_id'],
                'academic_year_id' => AcademicYear::factory()->create(['school_id' => $attributes['school_id']])->id,
            ])->id,
            'transfer_number' => 'TRF-'.fake()->unique()->numberBetween(10000, 99999),
            'production_unit_id' => fn (array $attributes): int => ProductionUnit::factory()->create(['school_id' => $attributes['school_id']])->id,
            'from_store_id' => fn (array $attributes): int => Store::factory()->create(['school_id' => $attributes['school_id'], 'code' => 'FARM'])->id,
            'to_store_id' => fn (array $attributes): int => Store::factory()->create(['school_id' => $attributes['school_id'], 'code' => 'KITCHEN'])->id,
            'transfer_date' => now()->toDateString(),
            'item_id' => fn (array $attributes): int => InventoryItem::factory()->create(['school_id' => $attributes['school_id']])->id,
            'quantity' => 200,
            'unit' => 'kg',
            'unit_cost_minor' => 80,
            'total_cost_minor' => 16000,
            'currency' => 'USD',
            'dispatched_by' => User::factory(),
            'status' => 'dispatched',
        ];
    }
}
