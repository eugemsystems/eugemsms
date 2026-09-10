<?php

declare(strict_types=1);

namespace Modules\Comms\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Comms\Models\MessageConversation;
use Modules\Comms\Models\WhatsAppBusinessAccount;
use Modules\Core\Models\School;

/**
 * @extends Factory<MessageConversation>
 */
class MessageConversationFactory extends Factory
{
    protected $model = MessageConversation::class;

    public function definition(): array
    {
        return [
            'school_id' => School::factory(),
            'waba_id' => fn (array $attributes): int => WhatsAppBusinessAccount::factory()->create(['school_id' => $attributes['school_id']])->id,
            'contact_phone' => '+263771234567',
            'last_inbound_at' => now()->subHours(2),
            'session_expires_at' => now()->addHours(22),
            'conversation_category' => 'service',
            'opened_by_template_id' => null,
        ];
    }

    public function expired(): self
    {
        return $this->state(fn (): array => ['last_inbound_at' => now()->subHours(30), 'session_expires_at' => now()->subHours(6)]);
    }
}
