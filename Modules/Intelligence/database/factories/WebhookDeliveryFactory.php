<?php

declare(strict_types=1);

namespace Modules\Intelligence\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Core\Models\School;
use Modules\Intelligence\Models\WebhookDelivery;
use Modules\Intelligence\Models\WebhookSubscription;

/**
 * @extends Factory<WebhookDelivery>
 */
class WebhookDeliveryFactory extends Factory
{
    protected $model = WebhookDelivery::class;

    public function definition(): array
    {
        $school = School::factory();

        return [
            'school_id' => $school,
            'subscription_id' => WebhookSubscription::factory()->for($school),
            'event_name' => 'InvoiceIssued',
            'payload' => ['id' => 1],
            'attempt_count' => 0,
            'status' => 'pending',
        ];
    }
}
