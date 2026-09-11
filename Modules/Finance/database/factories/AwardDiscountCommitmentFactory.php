<?php

declare(strict_types=1);

namespace Modules\Finance\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Finance\Models\AwardDiscountCommitment;
use Modules\Finance\Models\DiscountAward;
use Modules\Finance\Models\DiscountScheme;
use Modules\Finance\Models\LearnerFeeAssignment;
use Modules\Finance\Models\LearnerFeeLine;

/**
 * @extends Factory<AwardDiscountCommitment>
 */
class AwardDiscountCommitmentFactory extends Factory
{
    protected $model = AwardDiscountCommitment::class;

    public function definition(): array
    {
        // `assignment_id` is passed explicitly rather than left to
        // LearnerFeeLineFactory's own default — see AwardUtilisationFactory's
        // own docblock for why.
        $assignment = LearnerFeeAssignment::factory()->create();

        return [
            'fee_line_id' => LearnerFeeLine::factory()->create(['assignment_id' => $assignment->id, 'school_id' => $assignment->school_id])->id,
            'award_id' => DiscountAward::factory(),
            'scheme_id' => DiscountScheme::factory(),
            'discount_minor' => 1000,
            'currency' => 'USD',
        ];
    }
}
