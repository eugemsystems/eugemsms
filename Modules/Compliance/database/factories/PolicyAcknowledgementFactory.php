<?php

declare(strict_types=1);

namespace Modules\Compliance\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Compliance\Models\Policy;
use Modules\Compliance\Models\PolicyAcknowledgement;
use Modules\Core\Models\School;
use Modules\People\Models\Staff;

/**
 * @extends Factory<PolicyAcknowledgement>
 */
class PolicyAcknowledgementFactory extends Factory
{
    protected $model = PolicyAcknowledgement::class;

    public function definition(): array
    {
        return [
            'school_id' => School::factory(),
            'policy_id' => fn (array $attributes): int => Policy::factory()->create(['school_id' => $attributes['school_id']])->id,
            'policy_version' => 'v1',
            'acknowledged_by_type' => 'staff',
            'acknowledged_by_id' => fn (array $attributes): int => Staff::factory()->create(['school_id' => $attributes['school_id']])->id,
            'acknowledged_at' => now(),
            'ip_address' => '127.0.0.1',
            'method' => 'portal',
        ];
    }
}
