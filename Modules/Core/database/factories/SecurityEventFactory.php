<?php

declare(strict_types=1);

namespace Modules\Core\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Core\Models\SecurityEvent;

/**
 * @extends Factory<SecurityEvent>
 */
class SecurityEventFactory extends Factory
{
    protected $model = SecurityEvent::class;

    public function definition(): array
    {
        return [
            'event_type' => 'repeated_login_failure',
            'severity' => 'warning',
            'description' => fake()->sentence(),
            'is_reviewed' => false,
            'occurred_at' => now(),
        ];
    }
}
