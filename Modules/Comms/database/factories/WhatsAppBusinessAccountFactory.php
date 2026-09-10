<?php

declare(strict_types=1);

namespace Modules\Comms\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Comms\Models\MessageGateway;
use Modules\Comms\Models\WhatsAppBusinessAccount;
use Modules\Core\Models\School;

/**
 * @extends Factory<WhatsAppBusinessAccount>
 */
class WhatsAppBusinessAccountFactory extends Factory
{
    protected $model = WhatsAppBusinessAccount::class;

    public function definition(): array
    {
        return [
            'school_id' => School::factory(),
            'gateway_id' => fn (array $attributes): int => MessageGateway::factory()->whatsapp()->create(['school_id' => $attributes['school_id']])->id,
            'waba_id' => (string) $this->faker->unique()->numerify('##############'),
            'display_phone_number' => '+263771234567',
            'display_name' => 'Test School',
            'display_name_status' => 'approved',
            'quality_rating' => 'green',
            'messaging_limit_tier' => '1k',
            'verified_at' => now()->subMonth(),
            'status' => 'active',
        ];
    }
}
