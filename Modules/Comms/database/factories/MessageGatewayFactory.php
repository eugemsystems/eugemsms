<?php

declare(strict_types=1);

namespace Modules\Comms\Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Comms\Models\MessageGateway;
use Modules\Core\Models\School;

/**
 * @extends Factory<MessageGateway>
 */
class MessageGatewayFactory extends Factory
{
    protected $model = MessageGateway::class;

    public function definition(): array
    {
        return [
            'school_id' => School::factory(),
            'channel' => 'sms',
            'driver' => 'bulksms_zw',
            'name' => 'BulkSMS Zimbabwe',
            'credentials' => json_encode(['api_key' => 'test-key']),
            'webhook_secret' => 'test-secret',
            'is_default' => true,
            'is_sandbox' => true,
            'priority' => 0,
            'health_status' => 'up',
            'is_active' => true,
            'created_by' => User::factory(),
        ];
    }

    public function whatsapp(): self
    {
        return $this->state(fn (): array => ['channel' => 'whatsapp', 'driver' => 'whatsapp_cloud_api', 'name' => 'WhatsApp Cloud API']);
    }

    public function email(): self
    {
        return $this->state(fn (): array => ['channel' => 'email', 'driver' => 'smtp', 'name' => 'School SMTP']);
    }

    public function push(): self
    {
        return $this->state(fn (): array => ['channel' => 'push', 'driver' => 'fcm', 'name' => 'Firebase Cloud Messaging']);
    }
}
