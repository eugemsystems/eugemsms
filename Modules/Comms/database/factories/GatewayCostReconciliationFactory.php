<?php

declare(strict_types=1);

namespace Modules\Comms\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Comms\Models\GatewayCostReconciliation;
use Modules\Comms\Models\MessageGateway;
use Modules\Core\Models\School;

/**
 * @extends Factory<GatewayCostReconciliation>
 */
class GatewayCostReconciliationFactory extends Factory
{
    protected $model = GatewayCostReconciliation::class;

    public function definition(): array
    {
        return [
            'school_id' => School::factory(),
            'gateway_id' => fn (array $attributes): int => MessageGateway::factory()->create(['school_id' => $attributes['school_id']])->id,
            'period_month' => now()->format('Y-m'),
            'system_recorded_minor' => 41200,
            'provider_invoiced_minor' => null,
            'variance_minor' => null,
            'provider_statement_file_id' => null,
            'status' => 'pending',
            'reconciled_by' => null,
        ];
    }
}
