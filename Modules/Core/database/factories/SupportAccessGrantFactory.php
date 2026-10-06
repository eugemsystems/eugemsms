<?php

declare(strict_types=1);

namespace Modules\Core\Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Core\Models\SupportAccessGrant;
use Modules\Core\Models\Tenant;

/**
 * @extends Factory<SupportAccessGrant>
 */
class SupportAccessGrantFactory extends Factory
{
    protected $model = SupportAccessGrant::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'granted_by' => User::factory(),
            'ticket_reference' => 'TKT-'.fake()->unique()->numerify('#####'),
            'reason' => 'Help the bursar understand a statement.',
            'expires_at' => now()->addHours(4),
        ];
    }
}
