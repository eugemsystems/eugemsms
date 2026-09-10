<?php

declare(strict_types=1);

namespace Modules\Stores\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Core\Models\School;
use Modules\Stores\Models\InventoryItem;
use Modules\Stores\Models\Store;
use Modules\Stores\Models\StoreItemSetting;

/**
 * @extends Factory<StoreItemSetting>
 */
class StoreItemSettingFactory extends Factory
{
    protected $model = StoreItemSetting::class;

    public function definition(): array
    {
        $school = School::factory();

        return [
            'school_id' => $school,
            'store_id' => Store::factory()->for($school),
            'item_id' => InventoryItem::factory()->for($school),
            'is_stocked' => true,
        ];
    }
}
