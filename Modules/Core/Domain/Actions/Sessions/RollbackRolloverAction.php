<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Actions\Sessions;

use Modules\Core\Domain\Actions\Action;
use Modules\Core\Domain\DataObjects\Sessions\RollbackRolloverData;
use Modules\Core\Domain\Exceptions\InvalidStateTransitionException;
use Modules\Core\Domain\Support\RolloverStatus;
use Modules\Core\Models\PeriodRollover;

/**
 * ACT-RollbackRollover (Book A CORE-03 §4). A *failed* roll-over already
 * had its database changes undone automatically — `DB::transaction()`
 * inside `ExecuteRolloverAction` guarantees that (BR-CORE-03-017). What
 * this closes out is the record itself: acknowledging the failed attempt
 * as formally rolled back (rather than leaving it `failed` indefinitely,
 * which would keep tripping BR-CORE-03-022's one-roll-over-at-a-time
 * lock — a `failed` roll-over doesn't block a retry, but a stale one
 * left un-acknowledged is still an operational loose end this closes).
 */
final class RollbackRolloverAction extends Action
{
    private const RETENTION_DAYS = 30;

    public function execute(RollbackRolloverData $data): void
    {
        $rollover = PeriodRollover::withoutGlobalScopes()->findOrFail($data->rolloverId);

        if ($rollover->status !== RolloverStatus::Failed) {
            throw new InvalidStateTransitionException(
                "Cannot roll back a roll-over in status [{$rollover->status->value}] — it must have failed.",
                ['rollover_id' => $rollover->id, 'status' => $rollover->status->value],
            );
        }

        $deadline = ($rollover->completed_at ?? $rollover->started_at ?? $rollover->created_at)
            ?->copy()->addDays(self::RETENTION_DAYS);

        if ($deadline !== null && $deadline->isPast()) {
            throw new InvalidStateTransitionException(
                'This roll-over is past its retention window and can no longer be rolled back.',
                ['rollover_id' => $rollover->id],
            );
        }

        $this->transaction(function () use ($rollover, $data): void {
            $rollover->status = RolloverStatus::RolledBack;
            $rollover->exception_report = array_merge($rollover->exception_report ?? [], [
                'rolled_back_by' => $data->performedByUserId,
                'rolled_back_reason' => $data->reason,
            ]);
            $rollover->save();
        });
    }
}
