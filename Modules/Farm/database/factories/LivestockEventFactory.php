<?php

declare(strict_types=1);

namespace Modules\Farm\Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Core\Models\School;
use Modules\Farm\Models\Livestock;
use Modules\Farm\Models\LivestockEvent;

/**
 * @extends Factory<LivestockEvent>
 */
class LivestockEventFactory extends Factory
{
    protected $model = LivestockEvent::class;

    public function definition(): array
    {
        return [
            'school_id' => School::factory(),
            'livestock_id' => fn (array $attributes): int => Livestock::factory()->create(['school_id' => $attributes['school_id']])->id,
            'event_type' => 'weighing',
            'event_date' => now()->toDateString(),
            'head_count_affected' => 1,
            'recorded_by' => User::factory(),
        ];
    }
}
