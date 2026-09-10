<?php

declare(strict_types=1);

namespace Modules\Utilities\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Core\Models\School;
use Modules\Utilities\Models\LoadSheddingSchedule;

/**
 * @extends Factory<LoadSheddingSchedule>
 */
class LoadSheddingScheduleFactory extends Factory
{
    protected $model = LoadSheddingSchedule::class;

    public function definition(): array
    {
        return [
            'school_id' => School::factory(),
            'schedule_date' => now()->toDateString(),
            'starts_at' => '06:00:00',
            'ends_at' => '10:00:00',
            'source' => 'published',
        ];
    }
}
