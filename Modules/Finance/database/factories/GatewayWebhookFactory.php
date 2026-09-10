<?php

declare(strict_types=1);

namespace Modules\Finance\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Finance\Models\GatewayWebhook;

/**
 * @extends Factory<GatewayWebhook>
 */
class GatewayWebhookFactory extends Factory
{
    protected $model = GatewayWebhook::class;

    public function definition(): array
    {
        $payload = json_encode(['event' => 'settlement', 'reference' => fake()->unique()->numerify('REF######')]);

        return [
            'driver' => 'fake',
            'raw_headers' => ['content-type' => 'application/json'],
            'raw_payload' => $payload,
            'payload_hash' => hash('sha256', (string) $payload),
            'signature_valid' => true,
            'processing_status' => 'received',
            'received_at' => now(),
        ];
    }
}
