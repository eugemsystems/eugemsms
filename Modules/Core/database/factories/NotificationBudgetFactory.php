<?php

declare(strict_types=1);

namespace Modules\Core\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Core\Models\NotificationBudget;
use Modules\Core\Models\School;

/**
 * @extends Factory<NotificationBudget>
 */
class NotificationBudgetFactory extends Factory
{
    protected $model = NotificationBudget::class;

    public function definition(): array
    {
        return [
            'school_id' => School::factory(),
            'period_month' => now()->format('Y-m'),
            'channel' => 'sms',
            'cap_minor' => 10000,
            'spent_minor' => 0,
            'currency' => 'USD',
            'warn_at_percent' => 80,
            'is_hard_stop' => true,
        ];
    }
}
