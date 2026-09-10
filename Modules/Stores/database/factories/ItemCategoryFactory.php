<?php

declare(strict_types=1);

namespace Modules\Stores\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Core\Models\School;
use Modules\Stores\Models\ItemCategory;

/**
 * @extends Factory<ItemCategory>
 */
class ItemCategoryFactory extends Factory
{
    protected $model = ItemCategory::class;

    public function definition(): array
    {
        return [
            'school_id' => School::factory(),
            'code' => 'PROV',
            'name' => 'Provisions',
            'is_active' => true,
        ];
    }
}
