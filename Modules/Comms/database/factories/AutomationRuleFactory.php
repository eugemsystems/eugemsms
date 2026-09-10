<?php

declare(strict_types=1);

namespace Modules\Comms\Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Comms\Models\AutomationRule;
use Modules\Core\Models\School;

/**
 * @extends Factory<AutomationRule>
 */
class AutomationRuleFactory extends Factory
{
    protected $model = AutomationRule::class;

    public function definition(): array
    {
        return [
            'school_id' => School::factory(),
            'name' => 'Fee overdue — 30 day notice '.$this->faker->unique()->numerify('###'),
            'notification_key' => 'fee.overdue.stage_2',
            'trigger_type' => 'scheduled_scan',
            'event_name' => null,
            'schedule_cron' => '0 8 * * *',
            'scan_entity' => 'invoice',
            'audience_override' => null,
            'channel_override' => null,
            'template_key_override' => null,
            'delay_minutes' => 0,
            'throttle_key' => 'invoice.id',
            'throttle_window_hours' => 168,
            'is_active' => false,
            'estimated_monthly_cost_minor' => null,
            'estimated_monthly_currency' => null,
            'created_by' => User::factory(),
        ];
    }

    public function eventTriggered(): self
    {
        return $this->state(fn (): array => [
            'trigger_type' => 'event', 'event_name' => 'InvoiceOverdue', 'schedule_cron' => null, 'scan_entity' => null,
        ]);
    }

    public function active(): self
    {
        return $this->state(fn (): array => ['is_active' => true, 'estimated_monthly_cost_minor' => 500, 'estimated_monthly_currency' => 'USD']);
    }
}
