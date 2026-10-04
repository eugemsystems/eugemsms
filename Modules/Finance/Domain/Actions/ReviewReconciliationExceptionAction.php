<?php

declare(strict_types=1);

namespace Modules\Finance\Domain\Actions;

use Illuminate\Support\Carbon;
use InvalidArgumentException;
use Modules\Core\Domain\Actions\Action;
use Modules\Finance\Models\ReconciliationRun;

/**
 * ACT-ReviewReconciliationException (Book B FIN-05 §5/BR-FIN-05-013,
 * `finance.reconciliation.resolve`). "Nothing auto-resolves. The job
 * produces the exception list; a human clears each one, and the
 * clearing is recorded" (spec §5) — `RunReconciliationAction` builds
 * that list but nothing records a clearing against it, and
 * `reconciliation_runs.reviewed_by`/`reviewed_at` existed as columns
 * nothing ever wrote to. Each exception entry gets its own
 * `resolved_at`/`resolution_note`/`resolved_by`, in place, by array
 * index (stable — this array is never reordered after the run that
 * created it); once every exception on the run carries one, the run
 * itself is marked reviewed.
 */
final class ReviewReconciliationExceptionAction extends Action
{
    public function execute(int $reconciliationRunId, int $exceptionIndex, string $resolutionNote, int $reviewedByUserId): ReconciliationRun
    {
        $run = ReconciliationRun::findOrFail($reconciliationRunId);
        $exceptions = $run->exceptions ?? [];

        if (! array_key_exists($exceptionIndex, $exceptions)) {
            throw new InvalidArgumentException("No exception at index {$exceptionIndex} on reconciliation run #{$reconciliationRunId}.");
        }

        // Checked before this index's own write below — otherwise every
        // element of $exceptions would provably carry 'resolved_at' by
        // the time of the check, collapsing it to an always-true tautology.
        $everyOtherAlreadyResolved = collect($exceptions)
            ->except([$exceptionIndex])
            ->every(fn (array $exception): bool => isset($exception['resolved_at']));

        $exceptions[$exceptionIndex]['resolved_at'] = Carbon::now()->toIso8601String();
        $exceptions[$exceptionIndex]['resolution_note'] = $resolutionNote;
        $exceptions[$exceptionIndex]['resolved_by'] = $reviewedByUserId;

        $allResolved = $everyOtherAlreadyResolved;

        return $this->transaction(function () use ($run, $exceptions, $allResolved, $reviewedByUserId): ReconciliationRun {
            $run->update([
                'exceptions' => $exceptions,
                ...($allResolved ? ['reviewed_by' => $reviewedByUserId, 'reviewed_at' => Carbon::now()] : []),
            ]);

            return $run->fresh();
        });
    }
}
