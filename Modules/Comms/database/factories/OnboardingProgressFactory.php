<?php

declare(strict_types=1);

namespace Modules\Comms\Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Comms\Models\OnboardingProgress;

/**
 * @extends Factory<OnboardingProgress>
 */
class OnboardingProgressFactory extends Factory
{
    protected $model = OnboardingProgress::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'persona' => 'parent',
            'steps_completed' => [],
            'completed_at' => null,
            'skipped_at' => null,
        ];
    }
}
