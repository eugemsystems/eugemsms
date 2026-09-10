<?php

declare(strict_types=1);

namespace Modules\Comms\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Comms\Models\CalendarSource;

/**
 * @extends Factory<CalendarSource>
 */
class CalendarSourceFactory extends Factory
{
    protected $model = CalendarSource::class;

    public function definition(): array
    {
        return [
            'module_code' => 'CORE-03',
            'source_type' => 'test_source_'.$this->faker->unique()->numerify('###'),
            'default_colour' => '#3366ff',
            'default_audience_scope' => 'whole_school',
            'is_active' => true,
        ];
    }
}
