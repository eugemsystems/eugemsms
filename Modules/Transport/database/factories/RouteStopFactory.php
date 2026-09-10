<?php

declare(strict_types=1);

namespace Modules\Transport\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Core\Models\School;
use Modules\Transport\Models\Route;
use Modules\Transport\Models\RouteStop;

/**
 * @extends Factory<RouteStop>
 */
class RouteStopFactory extends Factory
{
    protected $model = RouteStop::class;

    public function definition(): array
    {
        return [
            'school_id' => School::factory(),
            'route_id' => fn (array $attributes): int => Route::factory()->create(['school_id' => $attributes['school_id']])->id,
            'sequence' => 1,
            'name' => 'Corner shops stop',
        ];
    }
}
