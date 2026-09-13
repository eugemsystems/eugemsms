<?php

declare(strict_types=1);

namespace Modules\Finance\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Core\Models\School;
use Modules\Finance\Models\Invoice;
use Modules\Finance\Models\ReminderSchedule;
use Modules\Finance\Models\ReminderSent;

/**
 * @extends Factory<ReminderSent>
 */
class ReminderSentFactory extends Factory
{
    protected $model = ReminderSent::class;

    public function definition(): array
    {
        $school = School::factory();

        return [
            'school_id' => $school,
            'schedule_id' => ReminderSchedule::factory()->for($school),
            'invoice_id' => Invoice::factory()->for($school),
            'balance_at_send_minor' => 10000,
            'sent_at' => now(),
        ];
    }
}
