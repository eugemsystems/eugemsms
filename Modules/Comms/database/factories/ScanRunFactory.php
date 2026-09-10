<?php

declare(strict_types=1);

namespace Modules\Comms\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Comms\Models\AutomationRule;
use Modules\Comms\Models\ScanRun;
use Modules\Core\Models\School;

/**
 * @extends Factory<ScanRun>
 */
class ScanRunFactory extends Factory
{
    protected $model = ScanRun::class;

    public function definition(): array
    {
        return [
            'school_id' => School::factory(),
            'rule_id' => fn (array $attributes): int => AutomationRule::factory()->create(['school_id' => $attributes['school_id']])->id,
            'ran_at' => now(),
            'records_scanned' => 10,
            'records_matched' => 2,
            'notifications_dispatched' => 2,
            'duration_ms' => 120,
            'status' => 'completed',
            'error' => null,
        ];
    }
}
