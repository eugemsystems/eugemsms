<?php

declare(strict_types=1);

namespace Modules\Comms\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Comms\Models\MessageSegment;
use Modules\Core\Models\Notification;
use Modules\Core\Models\School;

/**
 * @extends Factory<MessageSegment>
 */
class MessageSegmentFactory extends Factory
{
    protected $model = MessageSegment::class;

    public function definition(): array
    {
        return [
            'school_id' => School::factory(),
            'notification_id' => fn (array $attributes): int => Notification::factory()->create(['school_id' => $attributes['school_id']])->id,
            'encoding' => 'gsm7',
            'character_count' => 120,
            'segment_count' => 1,
            'rate_card_id' => null,
            'cost_minor' => 2,
            'currency' => 'USD',
        ];
    }
}
