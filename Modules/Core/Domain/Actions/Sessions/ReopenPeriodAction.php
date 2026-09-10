<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Actions\Sessions;

use Illuminate\Support\Carbon;
use Modules\Core\Domain\Actions\Action;
use Modules\Core\Domain\DataObjects\Sessions\ReopenPeriodData;
use Modules\Core\Domain\DataObjects\Sessions\TransitionPeriodData;
use Modules\Core\Domain\Events\Sessions\PeriodReopened;
use Modules\Core\Domain\Exceptions\InvalidStateTransitionException;
use Modules\Core\Domain\Support\PeriodState;
use Modules\Core\Models\PeriodReopenRequest;
use Modules\Core\Models\Term;

/**
 * ACT-ReopenPeriod (Book A CORE-03 §4). BR-CORE-03-010: approves a
 * pending `PeriodReopenRequest` and performs the `LOCKED → OPEN`
 * transition through `ACT-TransitionPeriodState` — the approver must be
 * a different user from the requester (enforced there too; checked here
 * first for a clearer error). BR-CORE-03-011: notifying the head, bursar,
 * and tenant owner belongs to CORE-09 (Notification Orchestration Bus,
 * not built yet) — deferred the same way audit logging was in CORE-02.
 */
final class ReopenPeriodAction extends Action
{
    public function __construct(
        private readonly TransitionPeriodStateAction $transitionPeriodState,
    ) {}

    public function execute(ReopenPeriodData $data): Term
    {
        $request = PeriodReopenRequest::withoutGlobalScopes()->findOrFail($data->requestId);

        if ($request->status !== 'pending') {
            throw new InvalidStateTransitionException(
                "This reopen request is already {$request->status}.",
                ['request_id' => $request->id],
            );
        }

        if ($data->approvedByUserId === $request->requested_by) {
            throw new InvalidStateTransitionException(
                'The approver must be a different user from the one who requested the reopen.',
                ['request_id' => $request->id],
            );
        }

        return $this->transaction(function () use ($request, $data): Term {
            $term = $this->transitionPeriodState->execute(new TransitionPeriodData(
                termId: $request->term_id,
                periodType: $request->period_type,
                toState: PeriodState::Open,
                performedByUserId: $request->requested_by,
                reason: $request->reason,
                approvedByUserId: $data->approvedByUserId,
                ipAddress: $data->ipAddress,
            ));

            $request->status = 'approved';
            $request->approved_by = $data->approvedByUserId;
            $request->approved_at = Carbon::now();
            $request->save();

            event(new PeriodReopened($term, $request->period_type, $request));

            return $term;
        });
    }
}
