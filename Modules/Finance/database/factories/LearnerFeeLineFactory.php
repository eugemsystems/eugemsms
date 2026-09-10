<?php

declare(strict_types=1);

namespace Modules\Finance\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Finance\Models\FeeComponent;
use Modules\Finance\Models\LearnerFeeAssignment;
use Modules\Finance\Models\LearnerFeeLine;

/**
 * @extends Factory<LearnerFeeLine>
 */
class LearnerFeeLineFactory extends Factory
{
    protected $model = LearnerFeeLine::class;

    public function definition(): array
    {
        $assignment = LearnerFeeAssignment::factory();

        return [
            'school_id' => fn (array $attrs) => LearnerFeeAssignment::find($attrs['assignment_id'])->school_id ?? null,
            'assignment_id' => $assignment,
            'component_id' => FeeComponent::factory(),
            'billing_basis' => 'flat_per_term',
            'quantity' => 1,
            'gross_minor' => 10000,
            'proration_factor' => 1,
            'discount_minor' => 0,
            'net_minor' => 10000,
            'currency' => 'USD',
        ];
    }
}
