<?php

declare(strict_types=1);

namespace Modules\Core\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Core\Models\NotificationTemplate;

/**
 * @extends Factory<NotificationTemplate>
 */
class NotificationTemplateFactory extends Factory
{
    protected $model = NotificationTemplate::class;

    public function definition(): array
    {
        return [
            'school_id' => null,
            'key' => 'fee.payment_received',
            'channel' => 'sms',
            'locale' => 'en_ZW',
            'body' => 'Dear {{ guardian.name }}, payment received for {{ learner.first_name }}.',
            'is_active' => true,
        ];
    }
}
