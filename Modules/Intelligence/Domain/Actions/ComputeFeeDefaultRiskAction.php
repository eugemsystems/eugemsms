<?php

declare(strict_types=1);

namespace Modules\Intelligence\Domain\Actions;

use Illuminate\Support\Carbon;
use Modules\Core\Domain\Actions\Action;
use Modules\Finance\Models\Invoice;
use Modules\Intelligence\Models\FeeDefaultRiskScore;

/**
 * ACT-ComputeFeeDefaultRisk (Book J INT-03 §2/BR-INT-03-006). A
 * household-level signal — every overdue invoice billed to the same
 * guardian is grouped together, since `Modules\Finance\Models\Invoice`'s
 * own `billed_party_id` already IS the paying guardian for
 * `billed_party_type = 'guardian'`. Recommends an action; never
 * triggers one — `FIN-03`'s own reminder ladder is untouched.
 */
final class ComputeFeeDefaultRiskAction extends Action
{
    /**
     * @return array<int, FeeDefaultRiskScore>
     */
    public function execute(int $schoolId): array
    {
        $today = Carbon::today();

        $overdue = Invoice::where('school_id', $schoolId)
            ->where('billed_party_type', 'guardian')
            ->where('balance_minor', '>', 0)
            ->where('due_date', '<', $today)
            ->where('status', '!=', 'void')
            ->get();

        $results = [];

        foreach ($overdue->groupBy('billed_party_id') as $guardianId => $invoices) {
            $worst = $invoices->sortByDesc(fn (Invoice $i): int => (int) $today->diffInDays($i->due_date, absolute: true))->first();
            $maxDaysOverdue = (int) $today->diffInDays($worst->due_date, absolute: true);
            $severity = min(100.0, round($maxDaysOverdue / 90 * 100, 2));

            $recommendedAction = match (true) {
                $maxDaysOverdue >= 60 => 'early_contact',
                $maxDaysOverdue >= 30 => 'offer_payment_plan',
                default => null,
            };

            $factors = [[
                'indicator' => 'fee_arrears',
                'plain_language' => "Fee account is {$maxDaysOverdue} days overdue across {$invoices->count()} invoice(s)",
                'weight' => 100, 'contribution' => $severity,
                'source' => 'FIN-03 invoices, days_overdue',
            ]];

            $results[] = $this->transaction(fn (): FeeDefaultRiskScore => FeeDefaultRiskScore::updateOrCreate(
                ['school_id' => $schoolId, 'guardian_id' => $guardianId],
                [
                    'student_id' => $worst->student_id, 'risk_score' => $severity,
                    'contributing_factors' => $factors, 'recommended_action' => $recommendedAction,
                    'computed_at' => Carbon::now(),
                ],
            ));
        }

        return $results;
    }
}
