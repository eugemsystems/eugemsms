<?php

declare(strict_types=1);

namespace Modules\Finance\Domain\Listeners;

use Modules\Finance\Models\DiscountAward;
use Modules\People\Domain\Events\LearnerWithdrawn;

/**
 * Book K FIN-07 §4/BR-FIN-07-015 (AC-FIN-07-007). Keys off `PPL-01`'s
 * own `LearnerWithdrawn` event, exactly as `FIN-02`'s pro-rata credit
 * is documented to — never off the `students.status` column directly.
 * Ends the award going forward only; already-posted `award_utilisation`/
 * invoices for prior terms are never touched (BR-FIN-07-012's "no
 * retroactive re-invoicing" applies here too).
 */
final class EndAwardsOnLearnerWithdrawnListener
{
    public function handle(LearnerWithdrawn $event): void
    {
        DiscountAward::withoutGlobalScopes()
            ->where('student_id', $event->student->id)
            ->where('status', 'active')
            ->get()
            ->each(fn (DiscountAward $award) => $award->update([
                'status' => 'ended',
                'effective_to' => $event->exitedOn->toDateString(),
            ]));
    }
}
