<?php

declare(strict_types=1);

namespace Modules\Comms\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Comms\Models\MessagingGatewayWebhook;
use Modules\Core\Models\School;

/**
 * @extends Factory<MessagingGatewayWebhook>
 */
class MessagingGatewayWebhookFactory extends Factory
{
    protected $model = MessagingGatewayWebhook::class;

    public function definition(): array
    {
        return [
            'school_id' => School::factory(),
            'gateway_id' => null,
            'driver' => 'bulksms_zw',
            'event_type' => 'delivered',
            'raw_headers' => ['X-Signature' => 'test'],
            'raw_payload' => json_encode(['status' => 'delivered']),
            'payload_hash' => hash('sha256', (string) $this->faker->unique()->uuid()),
            'signature_valid' => true,
            'notification_id' => null,
            'processing_status' => 'received',
            'processing_error' => null,
            'received_at' => now(),
            'processed_at' => null,
        ];
    }
}
