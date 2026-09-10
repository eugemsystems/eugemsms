<?php

declare(strict_types=1);

namespace Modules\Comms\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Comms\Models\MeetingProvider;
use Modules\Core\Models\School;

/**
 * @extends Factory<MeetingProvider>
 */
class MeetingProviderFactory extends Factory
{
    protected $model = MeetingProvider::class;

    public function definition(): array
    {
        return [
            'school_id' => School::factory(),
            'provider' => 'zoom',
            'credentials' => 'test-s2s-oauth-credentials',
            'account_email' => 'meetings@example.com',
            'webhook_secret' => 'test-webhook-secret',
            'is_active' => true,
        ];
    }
}
