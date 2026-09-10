<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Actions\Sessions;

use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Modules\Core\Domain\Actions\Action;
use Modules\Core\Domain\DataObjects\Sessions\TransitionPeriodData;
use Modules\Core\Domain\Events\Sessions\PeriodClosed;
use Modules\Core\Domain\Events\Sessions\PeriodStateChanged;
use Modules\Core\Domain\Exceptions\InvalidStateTransitionException;
use Modules\Core\Domain\Registry\CloseChecklistRegistry;
use Modules\Core\Domain\Support\PeriodState;
use Modules\Core\Domain\Support\PeriodType;
use Modules\Core\Models\PeriodStateTransition;
use Modules\Core\Models\Term;

/**
 * ACT-TransitionPeriodState (Book A CORE-03 §3/§4). BR-CORE-03-008: the
 * single gateway for every state change — nothing else may write
 * `academic_state`/`financial_state` (`GuardsPeriodStateWrites` on the
 * model enforces this). BR-CORE-03-009: every transition writes an
 * immutable `period_state_transitions` row in the same call.
 *
 * `LOCKED → OPEN` requires `approvedByUserId` to be set and distinct
 * from `performedByUserId` (BR-CORE-03-010's two-approver rule) — only
 * `ReopenPeriodAction` calls this gateway for that transition, after
 * `RequestPeriodReopenAction`'s request has actually been approved by a
 * second, different user. A caller asking this gateway for
 * `LOCKED → OPEN` without both distinct actors present is refused.
 */
final class TransitionPeriodStateAction extends Action
{
    public function execute(TransitionPeriodData $data): Term
    {
        // Not Term::query(): that carries BelongsToSchool's global scope,
        // filtered by the *ambient* SchoolContext — this gateway is
        // called from many contexts (a roll-over handler, a queued job,
        // a reopen approval) that have no reason to already have the
        // right school set. The term id alone is enough to load it.
        $term = Term::withoutGlobalScopes()->findOrFail($data->termId);
        $from = $term->stateFor($data->periodType);
        $to = $data->toState;

        $this->assertLegalTransition($term, $data, $from, $to);

        return $this->transaction(function () use ($term, $data, $from, $to): Term {
            $term->stateTransitionAuthorized = true;

            if ($data->periodType === PeriodType::Academic) {
                $term->academic_state = $to;

                if ($to === PeriodState::Locked) {
                    $term->academic_closed_at = Carbon::now();
                    $term->academic_closed_by = $data->performedByUserId;
                }
            } else {
                $term->financial_state = $to;

                if ($to === PeriodState::Locked) {
                    $term->financial_closed_at = Carbon::now();
                    $term->financial_closed_by = $data->performedByUserId;
                }
            }

            $term->save();
            $term->stateTransitionAuthorized = false;

            PeriodStateTransition::create([
                'school_id' => $term->school_id,
                'term_id' => $term->id,
                'period_type' => $data->periodType,
                'from_state' => $from,
                'to_state' => $to,
                'reason' => $data->reason,
                'performed_by' => $data->performedByUserId,
                'approved_by' => $data->approvedByUserId,
                'ip_address' => $data->ipAddress,
                'occurred_at' => now(),
            ]);

            event(new PeriodStateChanged($term, $data->periodType, $from, $to));

            if ($to === PeriodState::Locked) {
                event(new PeriodClosed($term, $data->periodType));
            }

            return $term;
        });
    }

    private function assertLegalTransition(Term $term, TransitionPeriodData $data, PeriodState $from, PeriodState $to): void
    {
        $legal = match (true) {
            $from === PeriodState::Planned && $to === PeriodState::Open => true,
            $from === PeriodState::Open && $to === PeriodState::SoftClosed => true,
            $from === PeriodState::SoftClosed && $to === PeriodState::Locked => true,
            $from === PeriodState::SoftClosed && $to === PeriodState::Open => true,
            $from === PeriodState::Locked && $to === PeriodState::Open => true,
            $from === PeriodState::Locked && $to === PeriodState::Archived => true,
            default => false,
        };

        if (! $legal) {
            throw new InvalidStateTransitionException(
                "Cannot transition {$data->periodType->value} state from {$from->value} to {$to->value}.",
                ['term_id' => $term->id, 'from' => $from->value, 'to' => $to->value],
            );
        }

        match (true) {
            $from === PeriodState::Planned && $to === PeriodState::Open => $this->assertCanOpen($term, $data->periodType),
            $from === PeriodState::SoftClosed && $to === PeriodState::Locked => $this->assertChecklistPasses($term, $data->periodType),
            $from === PeriodState::SoftClosed && $to === PeriodState::Open => $this->assertReasonGiven($data, 20),
            $from === PeriodState::Locked && $to === PeriodState::Open => $this->assertDualApproval($data),
            default => null,
        };
    }

    private function assertCanOpen(Term $term, PeriodType $type): void
    {
        $priorTerm = Term::withoutGlobalScopes()
            ->where('school_id', $term->school_id)
            ->where('id', '!=', $term->id)
            ->where('starts_on', '<', $term->starts_on)
            ->orderByDesc('starts_on')
            ->first();

        if ($priorTerm === null) {
            return;
        }

        $priorState = $priorTerm->stateFor($type);

        if (! in_array($priorState, [PeriodState::SoftClosed, PeriodState::Locked, PeriodState::Archived], true)) {
            throw new InvalidStateTransitionException(
                'The prior term must be at least soft-closed before this term can open.',
                ['term_id' => $term->id, 'prior_term_id' => $priorTerm->id],
            );
        }
    }

    private function assertChecklistPasses(Term $term, PeriodType $type): void
    {
        $items = CloseChecklistRegistry::for($type);

        foreach ($items as $item) {
            if (! $item->isBlocking()) {
                continue;
            }

            if (! $item->check($term)->passed) {
                throw new InvalidStateTransitionException(
                    "The {$type->value} close checklist has not passed — cannot lock.",
                    ['term_id' => $term->id, 'failing_item' => $item->code()],
                );
            }
        }
    }

    private function assertReasonGiven(TransitionPeriodData $data, int $minLength): void
    {
        if ($data->reason === null || mb_strlen($data->reason) < $minLength) {
            throw ValidationException::withMessages([
                'reason' => "A reason of at least {$minLength} characters is required to reopen a period.",
            ]);
        }
    }

    /**
     * BR-CORE-03-010: reopening a locked period needs a second approver
     * holding the same permission who is not the initiator.
     */
    private function assertDualApproval(TransitionPeriodData $data): void
    {
        $this->assertReasonGiven($data, 20);

        if ($data->approvedByUserId === null) {
            throw new InvalidStateTransitionException(
                'Reopening a locked period requires a second approver.',
                ['term_id' => $data->termId],
            );
        }

        if ($data->approvedByUserId === $data->performedByUserId) {
            throw new InvalidStateTransitionException(
                'The approver must be a different user from the one who requested the reopen.',
                ['term_id' => $data->termId],
            );
        }
    }
}
