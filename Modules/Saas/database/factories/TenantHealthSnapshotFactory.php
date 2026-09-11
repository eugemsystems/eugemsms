<?php

declare(strict_types=1);

namespace Modules\Saas\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;
use Modules\Core\Models\Tenant;
use Modules\Saas\Models\TenantHealthSnapshot;

/**
 * @extends Factory<TenantHealthSnapshot>
 */
class TenantHealthSnapshotFactory extends Factory
{
    protected $model = TenantHealthSnapshot::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'snapshot_date' => Carbon::today()->toDateString(),
            'active_schools' => 1,
            'active_learners' => 100,
            'subscription_status' => 'active',
            'last_login_days_ago' => 1,
            'module_adoption_percent' => 80.0,
            'open_support_tickets' => 0,
            'integrity_check_failures' => 0,
            'health_score' => 90.0,
        ];
    }
}
