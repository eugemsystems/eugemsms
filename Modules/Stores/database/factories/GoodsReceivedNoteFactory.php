<?php

declare(strict_types=1);

namespace Modules\Stores\Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Core\Models\AcademicYear;
use Modules\Core\Models\School;
use Modules\Core\Models\Term;
use Modules\Stores\Models\GoodsReceivedNote;
use Modules\Stores\Models\PurchaseOrder;
use Modules\Stores\Models\Supplier;

/**
 * @extends Factory<GoodsReceivedNote>
 */
class GoodsReceivedNoteFactory extends Factory
{
    protected $model = GoodsReceivedNote::class;

    public function definition(): array
    {
        return [
            'school_id' => School::factory(),
            'term_id' => fn (array $attributes): int => Term::factory()->create([
                'school_id' => $attributes['school_id'],
                'academic_year_id' => AcademicYear::factory()->create(['school_id' => $attributes['school_id']])->id,
            ])->id,
            'grn_number' => 'GRN/'.fake()->unique()->numberBetween(1000, 99999),
            'purchase_order_id' => fn (array $attributes): int => PurchaseOrder::factory()->create(['school_id' => $attributes['school_id']])->id,
            'supplier_id' => fn (array $attributes): int => Supplier::factory()->create(['school_id' => $attributes['school_id']])->id,
            'received_on' => now()->toDateString(),
            'received_by' => User::factory(),
            'total_value_minor' => 100000,
            'currency' => 'USD',
            'status' => 'received',
        ];
    }
}
