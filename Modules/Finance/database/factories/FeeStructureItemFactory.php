<?php

declare(strict_types=1);

namespace Modules\Finance\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Core\Models\School;
use Modules\Finance\Models\FeeComponent;
use Modules\Finance\Models\FeeStructure;
use Modules\Finance\Models\FeeStructureItem;

/**
 * @extends Factory<FeeStructureItem>
 */
class FeeStructureItemFactory extends Factory
{
    protected $model = FeeStructureItem::class;

    public function definition(): array
    {
        $school = School::factory();

        return [
            'school_id' => $school,
            'structure_id' => FeeStructure::factory()->for($school),
            'component_id' => FeeComponent::factory()->for($school),
            'billing_basis' => 'flat_per_term',
            'amount_minor' => 10000,
            'currency' => 'USD',
            'is_prorated' => true,
            'proration_basis' => 'day',
            'charge_frequency' => 'termly',
            'is_optional' => false,
        ];
    }
}
