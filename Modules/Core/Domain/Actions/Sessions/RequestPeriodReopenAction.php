<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Actions\Sessions;

use Illuminate\Validation\ValidationException;
use Modules\Core\Domain\Actions\Action;
use Modules\Core\Domain\DataObjects\Sessions\RequestPeriodReopenData;
use Modules\Core\Domain\Exceptions\InvalidStateTransitionException;
use Modules\Core\Domain\Support\PeriodState;
use Modules\Core\Models\PeriodReopenRequest;
use Modules\Core\Models\Term;

/**
 * Companion to `ACT-ReopenPeriod` (Book A CORE-03 §4/BR-CORE-03-010) —
 * see `period_reopen_requests`' migration note for why this exists as
 * its own step ahead of CORE-07. Only reachable for a `LOCKED` period:
 * `SOFT_CLOSED → OPEN` needs no second approver and goes straight
 * through `ACT-TransitionPeriodState` (see the legal-transitions table,
 * Book A CORE-03 §3).
 */
final class RequestPeriodReopenAction extends Action
{
    public function execute(RequestPeriodReopenData $data): PeriodReopenRequest
    {
        $term = Term::withoutGlobalScopes()->findOrFail($data->termId);
        $currentState = $term->stateFor($data->periodType);

        if ($currentState !== PeriodState::Locked) {
            throw new InvalidStateTransitionException(
                "Only a locked period can be requested for reopening — this one is {$currentState->value}.",
                ['term_id' => $term->id],
            );
        }

        if (mb_strlen($data->reason) < 20) {
            throw ValidationException::withMessages([
                'reason' => 'A reason of at least 20 characters is required to reopen a period.',
            ]);
        }

        $alreadyPending = PeriodReopenRequest::withoutGlobalScopes()
            ->where('term_id', $term->id)
            ->where('period_type', $data->periodType)
            ->where('status', 'pending')
            ->exists();

        if ($alreadyPending) {
            throw new InvalidStateTransitionException(
                'A reopen request for this period is already pending approval.',
                ['term_id' => $term->id],
            );
        }

        return $this->transaction(fn (): PeriodReopenRequest => PeriodReopenRequest::create([
            'school_id' => $term->school_id,
            'term_id' => $term->id,
            'period_type' => $data->periodType,
            'reason' => $data->reason,
            'requested_by' => $data->requestedByUserId,
            'requested_at' => now(),
            'status' => 'pending',
        ]));
    }
}
