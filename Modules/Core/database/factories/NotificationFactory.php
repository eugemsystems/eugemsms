<?php

declare(strict_types=1);

namespace Modules\Core\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Core\Models\Notification;
use Modules\Core\Models\School;

/**
 * @extends Factory<Notification>
 */
class NotificationFactory extends Factory
{
    protected $model = Notification::class;

    public function definition(): array
    {
        return [
            'school_id' => School::factory(),
            'notification_key' => 'fee.payment_received',
            'recipient_type' => 'user',
            'recipient_address' => fake()->e164PhoneNumber(),
            'channel' => 'sms',
            'body' => 'Test notification body.',
            'status' => 'sent',
            'created_at' => now(),
        ];
    }
}
