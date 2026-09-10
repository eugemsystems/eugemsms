<?php

declare(strict_types=1);

namespace Modules\Comms\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Comms\Models\MessageGateway;
use Modules\Comms\Models\ProviderRateCard;
use Modules\Core\Models\School;

/**
 * @extends Factory<ProviderRateCard>
 */
class ProviderRateCardFactory extends Factory
{
    protected $model = ProviderRateCard::class;

    public function definition(): array
    {
        $school = School::factory()->create();

        return [
            'school_id' => $school->id,
            'gateway_id' => MessageGateway::factory()->create(['school_id' => $school->id])->id,
            'destination_prefix' => '263',
            'rate_per_segment_minor' => 2,
            'whatsapp_utility_rate_minor' => 5,
            'whatsapp_marketing_rate_minor' => 8,
            'currency' => 'USD',
            'effective_from' => now()->subMonth()->toDateString(),
            'effective_to' => null,
        ];
    }
}
