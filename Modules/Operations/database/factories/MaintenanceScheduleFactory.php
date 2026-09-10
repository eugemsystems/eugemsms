<?php

declare(strict_types=1);

namespace Modules\Operations\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Core\Models\School;
use Modules\Operations\Models\MaintenanceAsset;
use Modules\Operations\Models\MaintenanceSchedule;

/**
 * @extends Factory<MaintenanceSchedule>
 */
class MaintenanceScheduleFactory extends Factory
{
    protected $model = MaintenanceSchedule::class;

    public function definition(): array
    {
        return [
            'school_id' => School::factory(),
            'maintenance_asset_id' => fn (array $attributes): int => MaintenanceAsset::factory()->create(['school_id' => $attributes['school_id']])->id,
            'name' => 'Generator 250-hour service',
            'trigger_type' => 'usage',
            'interval_units' => 250,
            'lead_time_days' => 7,
            'task_checklist' => ['Check oil', 'Replace filter', 'Test load'],
            'assigned_team' => 'in_house',
            'is_active' => true,
        ];
    }

    public function calendar(): self
    {
        return $this->state(fn (): array => [
            'trigger_type' => 'calendar',
            'interval_units' => null,
            'interval_days' => 90,
            'next_due_on' => now()->addDays(5)->toDateString(),
        ]);
    }
}
