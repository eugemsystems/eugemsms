<?php

declare(strict_types=1);

namespace Modules\Core\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Core\Models\ApprovalChain;
use Modules\Core\Models\School;

/**
 * @extends Factory<ApprovalChain>
 */
class ApprovalChainFactory extends Factory
{
    protected $model = ApprovalChain::class;

    public function definition(): array
    {
        return [
            'school_id' => School::factory(),
            'approvable_type' => 'purchase_order',
            'name' => 'Default chain',
            'is_default' => true,
            'priority' => 0,
            'is_active' => true,
        ];
    }
}
