<?php

declare(strict_types=1);

namespace Modules\Finance\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Core\Models\School;
use Modules\Finance\Models\ReminderSchedule;

/**
 * @extends Factory<ReminderSchedule>
 */
class ReminderScheduleFactory extends Factory
{
    protected $model = ReminderSchedule::class;

    public function definition(): array
    {
        return [
            'school_id' => School::factory(),
            'name' => 'Overdue reminder',
            'days_after_due' => 7,
            'minimum_balance_minor' => 0,
            'channels' => ['sms'],
            'template_key' => 'fee_reminder',
            'audience' => 'fee_responsible',
            'is_active' => true,
        ];
    }
}
