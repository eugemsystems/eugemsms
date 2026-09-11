<?php

declare(strict_types=1);

namespace Modules\Saas\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Saas\Models\ProductTour;

/**
 * @extends Factory<ProductTour>
 */
class ProductTourFactory extends Factory
{
    protected $model = ProductTour::class;

    public function definition(): array
    {
        return [
            'key' => strtolower(fake()->unique()->lexify('tour_????')),
            'persona' => 'teacher',
            'steps' => [
                ['title' => 'Welcome', 'body' => 'Here is your dashboard.'],
                ['title' => 'Take the register', 'body' => 'Mark attendance here.'],
            ],
        ];
    }
}
