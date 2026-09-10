<?php

declare(strict_types=1);

namespace Modules\Farm\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Core\Models\AcademicYear;
use Modules\Core\Models\School;
use Modules\Core\Models\Term;
use Modules\Farm\Models\FarmSale;
use Modules\Farm\Models\ProductionUnit;

/**
 * @extends Factory<FarmSale>
 */
class FarmSaleFactory extends Factory
{
    protected $model = FarmSale::class;

    public function definition(): array
    {
        return [
            'school_id' => School::factory(),
            'term_id' => fn (array $attributes): int => Term::factory()->create([
                'school_id' => $attributes['school_id'],
                'academic_year_id' => AcademicYear::factory()->create(['school_id' => $attributes['school_id']])->id,
            ])->id,
            'sale_number' => 'SALE-'.fake()->unique()->numberBetween(10000, 99999),
            'production_unit_id' => fn (array $attributes): int => ProductionUnit::factory()->create(['school_id' => $attributes['school_id']])->id,
            'sale_date' => now()->toDateString(),
            'buyer_name' => 'Local Grocer',
            'item_description' => 'Fresh vegetables',
            'quantity' => 50,
            'unit' => 'kg',
            'unit_price_minor' => 100,
            'total_minor' => 5000,
            'currency' => 'USD',
        ];
    }
}
