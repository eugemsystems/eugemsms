<?php

declare(strict_types=1);

namespace Modules\Finance\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;
use Modules\Core\Models\Term;
use Modules\Finance\Models\AwardUtilisation;
use Modules\Finance\Models\DiscountAward;
use Modules\Finance\Models\FeeComponent;
use Modules\Finance\Models\Journal;
use Modules\Finance\Models\LearnerFeeAssignment;
use Modules\Finance\Models\LearnerFeeLine;

/**
 * @extends Factory<AwardUtilisation>
 */
class AwardUtilisationFactory extends Factory
{
    protected $model = AwardUtilisation::class;

    public function definition(): array
    {
        // `assignment_id` is passed explicitly rather than left to
        // LearnerFeeLineFactory's own default — its `school_id`
        // closure reads `$attrs['assignment_id']` before Laravel has
        // necessarily resolved that key's own nested factory yet.
        $assignment = LearnerFeeAssignment::factory()->create();

        return [
            'award_id' => DiscountAward::factory(),
            'term_id' => Term::factory(),
            'component_id' => FeeComponent::factory(),
            'discount_minor' => 1000,
            'currency' => 'USD',
            'fee_line_id' => LearnerFeeLine::factory()->create(['assignment_id' => $assignment->id, 'school_id' => $assignment->school_id])->id,
            'journal_id' => Journal::factory(),
            'posted_at' => Carbon::now(),
        ];
    }
}
