<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Actions\Sessions;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Modules\Core\Domain\Actions\Action;
use Modules\Core\Domain\DataObjects\Sessions\ExecuteRolloverData;
use Modules\Core\Domain\DataObjects\Sessions\RolloverContext;
use Modules\Core\Domain\DataObjects\Sessions\RolloverResult;
use Modules\Core\Domain\Events\Sessions\RolloverCompleted;
use Modules\Core\Domain\Events\Sessions\RolloverFailed;
use Modules\Core\Domain\Exceptions\InvalidStateTransitionException;
use Modules\Core\Domain\Exceptions\RolloverHandlerFailedException;
use Modules\Core\Domain\Registry\RolloverHandlerRegistry;
use Modules\Core\Domain\Support\RolloverStatus;
use Modules\Core\Models\PeriodRollover;
use Modules\Core\Models\Term;

/**
 * ACT-ExecuteRollover (Book A CORE-03 §4). BR-CORE-03-017: runs in a
 * single database transaction — any blocking handler failure rolls back
 * the entire operation and leaves both terms exactly as they were.
 * BR-CORE-03-023: non-blocking failures are recorded and the roll-over
 * still completes. Runs synchronously — `JOB-RunPeriodRollover` (Book A
 * CORE-03 §12) is a thin queue wrapper around this same action, not a
 * second implementation of the transaction/rollback logic.
 */
final class ExecuteRolloverAction extends Action
{
    protected bool $transactional = false;

    public function execute(ExecuteRolloverData $data): RolloverResult
    {
        $rollover = PeriodRollover::withoutGlobalScopes()->findOrFail($data->rolloverId);

        if ($rollover->status !== RolloverStatus::Pending) {
            throw new InvalidStateTransitionException(
                "Cannot execute a roll-over in status [{$rollover->status->value}] — it must be pending.",
                ['rollover_id' => $rollover->id, 'status' => $rollover->status->value],
            );
        }

        try {
            $stepLog = DB::transaction(fn (): array => $this->runHandlers($rollover));

            $rollover->status = RolloverStatus::Completed;
            $rollover->completed_at = Carbon::now();
            $rollover->step_log = $stepLog;
            $rollover->approved_by = $data->approvedByUserId;
            $rollover->save();

            event(new RolloverCompleted($rollover));

            return new RolloverResult($rollover, RolloverStatus::Completed, $stepLog);
        } catch (RolloverHandlerFailedException $exception) {
            $rollover->status = RolloverStatus::Failed;
            $rollover->step_log = $exception->stepLog;
            $rollover->exception_report = [
                'failing_handler' => $exception->failingHandler,
                'message' => $exception->getMessage(),
            ];
            $rollover->save();

            event(new RolloverFailed($rollover, $exception->getMessage()));

            return new RolloverResult($rollover, RolloverStatus::Failed, $exception->stepLog);
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function runHandlers(PeriodRollover $rollover): array
    {
        $rollover->status = RolloverStatus::Running;
        $rollover->started_at = Carbon::now();
        $rollover->save();

        // Not $rollover->fromTerm/->toTerm: a BelongsTo relation builds a
        // fresh query against Term, which still carries Term's own
        // global scope regardless of $rollover having been loaded with
        // withoutGlobalScopes() — that bypass doesn't propagate through
        // relationships.
        $fromTerm = Term::withoutGlobalScopes()->findOrFail($rollover->from_term_id);
        $toTerm = Term::withoutGlobalScopes()->findOrFail($rollover->to_term_id);

        $context = new RolloverContext($rollover);
        $stepLog = [];
        $executed = [];

        foreach (RolloverHandlerRegistry::available() as $handler) {
            $result = $handler->execute($fromTerm, $toTerm, $context);

            $stepLog[] = [
                'handler' => $handler::class,
                'module' => $handler->moduleCode(),
                'blocking' => $handler->isBlocking(),
                'success' => $result->success,
                'message' => $result->message,
            ];

            if ($result->success) {
                $executed[] = $handler;

                continue;
            }

            if (! $handler->isBlocking()) {
                continue;
            }

            foreach (array_reverse($executed) as $previouslyExecuted) {
                $previouslyExecuted->rollback($fromTerm, $toTerm, $context);
            }

            $handlerClass = $handler::class;

            throw new RolloverHandlerFailedException(
                "Roll-over handler [{$handlerClass}] failed: {$result->message}",
                $handlerClass,
                $stepLog,
            );
        }

        return $stepLog;
    }
}
