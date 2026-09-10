<?php

declare(strict_types=1);

namespace Modules\Boarding\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Boarding\Models\MenuCycle;
use Modules\Boarding\Models\MenuDay;

/**
 * @extends Factory<MenuDay>
 */
class MenuDayFactory extends Factory
{
    protected $model = MenuDay::class;

    public function definition(): array
    {
        $cycle = MenuCycle::factory();

        return [
            'school_id' => $cycle,
            'cycle_id' => $cycle,
            'cycle_day' => 1,
            'meal' => 'lunch',
            'recipe_ids' => [],
        ];
    }
}
