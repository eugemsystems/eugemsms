<?php

declare(strict_types=1);

namespace Modules\Saas\Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;
use Modules\Saas\Models\BroadcastAnnouncement;

/**
 * @extends Factory<BroadcastAnnouncement>
 */
class BroadcastAnnouncementFactory extends Factory
{
    protected $model = BroadcastAnnouncement::class;

    public function definition(): array
    {
        return [
            'title' => fake()->sentence(4),
            'body' => fake()->paragraph(),
            'severity' => 'info',
            'target_tenant_ids' => null,
            'starts_at' => Carbon::now(),
            'ends_at' => null,
            'posted_by' => User::factory(),
        ];
    }
}
