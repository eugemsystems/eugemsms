<?php

declare(strict_types=1);

namespace Modules\Comms\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Comms\Models\WhatsAppBusinessAccount;
use Modules\Comms\Models\WhatsAppTemplate;
use Modules\Core\Models\School;

/**
 * @extends Factory<WhatsAppTemplate>
 */
class WhatsAppTemplateFactory extends Factory
{
    protected $model = WhatsAppTemplate::class;

    public function definition(): array
    {
        return [
            'school_id' => School::factory(),
            'waba_id' => fn (array $attributes): int => WhatsAppBusinessAccount::factory()->create(['school_id' => $attributes['school_id']])->id,
            'notification_key' => null,
            'meta_template_name' => 'school_notification',
            'category' => 'utility',
            'language' => 'en',
            'header_type' => 'none',
            'body_text' => '{{1}}',
            'footer_text' => null,
            'buttons' => null,
            'submitted_at' => now()->subWeek(),
            'review_status' => 'approved',
            'rejection_reason' => null,
            'approved_at' => now()->subDays(3),
            'meta_template_id' => (string) $this->faker->numerify('##########'),
        ];
    }

    public function pending(): self
    {
        return $this->state(fn (): array => ['review_status' => 'pending', 'approved_at' => null]);
    }
}
