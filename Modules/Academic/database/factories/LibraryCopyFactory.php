<?php

declare(strict_types=1);

namespace Modules\Academic\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Academic\Models\LibraryCopy;
use Modules\Academic\Models\LibraryItem;

/**
 * @extends Factory<LibraryCopy>
 */
class LibraryCopyFactory extends Factory
{
    protected $model = LibraryCopy::class;

    public function definition(): array
    {
        $item = LibraryItem::factory()->create();

        return [
            'school_id' => $item->school_id,
            'item_id' => $item->id,
            'accession_number' => 'ACC/'.$this->faker->unique()->numerify('######'),
            'condition' => 'good',
            'acquired_on' => now()->toDateString(),
            'status' => 'available',
        ];
    }
}
