<?php

declare(strict_types=1);

namespace Modules\Farm\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Core\Models\School;
use Modules\Farm\Models\Livestock;
use Modules\Farm\Models\ProductionUnit;

/**
 * @extends Factory<Livestock>
 */
class LivestockFactory extends Factory
{
    protected $model = Livestock::class;

    public function definition(): array
    {
        return [
            'school_id' => School::factory(),
            'production_unit_id' => fn (array $attributes): int => ProductionUnit::factory()->create(['school_id' => $attributes['school_id'], 'unit_type' => 'livestock'])->id,
            'tag_number' => 'TAG-'.fake()->unique()->numberBetween(1000, 9999),
            'species' => 'cattle',
            'is_herd_record' => false,
            'head_count' => 1,
            'purpose' => 'dairy',
            'status' => 'active',
        ];
    }

    public function herd(): self
    {
        return $this->state(fn (): array => ['is_herd_record' => true, 'head_count' => 50, 'tag_number' => null]);
    }
}
