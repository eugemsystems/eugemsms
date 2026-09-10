<?php

declare(strict_types=1);

namespace Modules\Comms\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Comms\Models\MeetingWebhookEvent;
use Modules\Core\Models\School;

/**
 * @extends Factory<MeetingWebhookEvent>
 */
class MeetingWebhookEventFactory extends Factory
{
    protected $model = MeetingWebhookEvent::class;

    public function definition(): array
    {
        return [
            'school_id' => School::factory(),
            'provider' => 'zoom',
            'event_type' => 'participant.joined',
            'raw_headers' => [],
            'raw_payload' => '{}',
            'payload_hash' => hash('sha256', (string) $this->faker->unique()->uuid()),
            'signature_valid' => true,
            'meeting_id' => null,
            'processing_status' => 'received',
            'received_at' => now(),
        ];
    }
}
