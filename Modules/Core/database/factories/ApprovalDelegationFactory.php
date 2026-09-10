<?php

declare(strict_types=1);

namespace Modules\Core\Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Core\Models\ApprovalDelegation;
use Modules\Core\Models\School;

/**
 * @extends Factory<ApprovalDelegation>
 */
class ApprovalDelegationFactory extends Factory
{
    protected $model = ApprovalDelegation::class;

    public function definition(): array
    {
        return [
            'school_id' => School::factory(),
            'delegator_id' => User::factory(),
            'delegate_id' => User::factory(),
            'starts_at' => now(),
            'ends_at' => now()->addDays(5),
            'is_active' => true,
        ];
    }
}
