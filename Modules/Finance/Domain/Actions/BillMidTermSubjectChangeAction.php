<?php

declare(strict_types=1);

namespace Modules\Finance\Domain\Actions;

use Modules\Academic\Models\LearnerSubjectEnrolment;
use Modules\Academic\Models\SubjectEnrolmentChange;
use Modules\Core\Domain\Actions\Action;
use Modules\Finance\Domain\DataObjects\BillMidTermSubjectChangeData;
use Modules\Finance\Domain\Events\LearnerFeeRecalculated;
use Modules\Finance\Models\LearnerFeeAssignment;
use Modules\Finance\Models\LearnerFeeLine;

/**
 * ACT-BillMidTermSubjectChange (Book B FIN-02 §4/BR-FIN-02-005
 * (AC-FIN-02-003/004)). Reacts to one `ACA-02` subject add or drop:
 * raises a pro-rated supplementary charge (add) or credit (drop)
 * against the learner's *current* fee assignment for the term, using
 * the proration factor `ACA-02` already snapshotted at change time —
 * this action never recomputes it.
 *
 * Wired automatically from `SubjectEnrolmentAdded`/
 * `SubjectEnrolmentDropped` by `RaiseMidTermSubjectChangeBillingListener`
 * (registered in `FinanceServiceProvider::boot()`), and remains fully
 * real and directly callable/testable on its own. A learner with no
 * `approved`/`invoiced` assignment yet for the term (billing hasn't
 * run) is a no-op: the eventual batch run computes the current subject
 * count fresh and needs no supplement.
 */
final class BillMidTermSubjectChangeAction extends Action
{
    public function execute(BillMidTermSubjectChangeData $data): ?LearnerFeeLine
    {
        $enrolment = LearnerSubjectEnrolment::findOrFail($data->enrolmentId);
        $change = SubjectEnrolmentChange::findOrFail($data->changeId);

        $assignment = LearnerFeeAssignment::query()
            ->where('student_id', $enrolment->student_id)
            ->where('term_id', $enrolment->term_id)
            ->whereIn('status', ['approved', 'invoiced'])
            ->with('structure.items')
            ->orderByDesc('id')
            ->first();

        if ($assignment === null) {
            return null;
        }

        $item = $assignment->structure->items->firstWhere('billing_basis', 'per_subject');

        if ($item === null) {
            return null;
        }

        $rateMap = $item->subject_rate_map ?? [];
        $groupCode = $enrolment->subjectGroup?->code;
        $rate = ($groupCode !== null ? ($rateMap["group:{$groupCode}"] ?? null) : null) ?? $item->unit_rate_minor ?? 0;

        $delta = (int) round($rate * (float) $change->proration_factor);
        $signedDelta = $change->change_type === 'dropped' ? -$delta : $delta;

        $subjectName = $enrolment->subject->name;
        $verb = $change->change_type === 'dropped' ? 'dropped' : 'added';
        $rateDisplay = number_format($rate / 100, 2);
        $creditedOrCharged = $change->change_type === 'dropped' ? 'credited' : 'charged';
        $amountDisplay = number_format(abs($delta) / 100, 2);

        $note = sprintf(
            '%s %s %s (effective %s). Rate %s × %s/%s teaching days remaining = %s %s. Pro-rated per structure item %s basis=per_subject, proration=%s.',
            $subjectName,
            $verb,
            $creditedOrCharged,
            $change->effective_from->toDateString(),
            $rateDisplay,
            $change->teaching_days_remaining,
            $change->term_teaching_days,
            $amountDisplay,
            $creditedOrCharged,
            $item->ulid,
            $item->proration_basis,
        );

        return $this->transaction(function () use ($assignment, $item, $signedDelta, $note, $change, $enrolment): LearnerFeeLine {
            $line = LearnerFeeLine::create([
                'school_id' => $assignment->school_id,
                'assignment_id' => $assignment->id,
                'component_id' => $item->component_id,
                'structure_item_id' => $item->id,
                'billing_basis' => 'per_subject',
                'quantity' => 1,
                'unit_rate_minor' => $item->unit_rate_minor,
                'gross_minor' => $signedDelta,
                'proration_factor' => $change->proration_factor,
                'discount_minor' => 0,
                'net_minor' => $signedDelta,
                'currency' => $item->currency,
                'calculation_note' => $note,
                'source_reference' => $enrolment->subject->ulid,
                'effective_from' => $change->effective_from,
            ]);

            event(new LearnerFeeRecalculated($line));

            return $line;
        });
    }
}
