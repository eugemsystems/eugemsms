<?php

declare(strict_types=1);

namespace Modules\Welfare\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Core\Models\School;
use Modules\Welfare\Models\ClinicStock;

/**
 * @extends Factory<ClinicStock>
 */
class ClinicStockFactory extends Factory
{
    protected $model = ClinicStock::class;

    public function definition(): array
    {
        return [
            'school_id' => School::factory(),
            'name' => 'Paracetamol 500mg',
            'category' => 'medication',
            'is_controlled' => false,
            'quantity_on_hand' => 100,
            'unit' => 'tablets',
            'reorder_level' => 20,
        ];
    }
}
