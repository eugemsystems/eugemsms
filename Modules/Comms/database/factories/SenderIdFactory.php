<?php

declare(strict_types=1);

namespace Modules\Comms\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Comms\Models\MessageGateway;
use Modules\Comms\Models\SenderId;
use Modules\Core\Models\School;

/**
 * @extends Factory<SenderId>
 */
class SenderIdFactory extends Factory
{
    protected $model = SenderId::class;

    public function definition(): array
    {
        return [
            'school_id' => School::factory(),
            'gateway_id' => fn (array $attributes): int => MessageGateway::factory()->create(['school_id' => $attributes['school_id']])->id,
            'sender_id' => 'SGCollege',
            'network' => 'econet',
            'registration_reference' => 'REF-001',
            'status' => 'approved',
            'submitted_at' => now()->subMonth(),
            'approved_at' => now()->subWeeks(3),
            'expires_on' => now()->addYear()->toDateString(),
        ];
    }
}
