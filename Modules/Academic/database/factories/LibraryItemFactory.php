<?php

declare(strict_types=1);

namespace Modules\Academic\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Academic\Models\LibraryItem;
use Modules\Core\Models\School;

/**
 * @extends Factory<LibraryItem>
 */
class LibraryItemFactory extends Factory
{
    protected $model = LibraryItem::class;

    public function definition(): array
    {
        return [
            'school_id' => School::factory(),
            'isbn' => '978-'.$this->faker->unique()->numerify('#########'),
            'title' => 'Combined Science Form 3',
            'author' => 'ZIMSEC',
            'item_category' => 'textbook',
            'replacement_cost_minor' => 1500,
            'currency' => 'USD',
            'is_active' => true,
        ];
    }
}
