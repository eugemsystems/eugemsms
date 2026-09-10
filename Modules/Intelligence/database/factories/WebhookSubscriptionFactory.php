<?php

declare(strict_types=1);

namespace Modules\Intelligence\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Core\Models\School;
use Modules\Intelligence\Models\ApiClient;
use Modules\Intelligence\Models\WebhookSubscription;

/**
 * @extends Factory<WebhookSubscription>
 */
class WebhookSubscriptionFactory extends Factory
{
    protected $model = WebhookSubscription::class;

    public function definition(): array
    {
        $school = School::factory();

        return [
            'school_id' => $school,
            'client_id' => ApiClient::factory()->for($school),
            'event_names' => ['InvoiceIssued'],
            'target_url' => 'https://example.test/webhooks/serp',
            'signing_secret' => 'test-signing-secret',
            'is_active' => true,
            'consecutive_failures' => 0,
        ];
    }
}
