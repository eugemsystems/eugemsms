<?php

declare(strict_types=1);

namespace Modules\Saas\Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;
use Modules\Core\Models\Tenant;
use Modules\Saas\Models\SupportTicket;

/**
 * @extends Factory<SupportTicket>
 */
class SupportTicketFactory extends Factory
{
    protected $model = SupportTicket::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'school_id' => null,
            'raised_by_user_id' => User::factory(),
            'subject' => fake()->sentence(6),
            'description' => fake()->paragraph(),
            'category' => 'how_to',
            'priority' => 'normal',
            'sla_due_at' => Carbon::now()->addHours(48),
            'assigned_vendor_staff_id' => null,
            'status' => 'open',
        ];
    }

    public function priority(string $priority): self
    {
        return $this->state(['priority' => $priority]);
    }

    public function status(string $status): self
    {
        return $this->state(['status' => $status]);
    }
}
