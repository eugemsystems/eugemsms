<?php

declare(strict_types=1);

namespace Modules\Finance\Domain\Actions;

use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Modules\Core\Domain\Actions\Action;
use Modules\Core\Domain\Exceptions\InvalidStateTransitionException;
use Modules\Finance\Domain\DataObjects\CommitBillingRunData;
use Modules\Finance\Domain\DataObjects\IssueInvoicesForAssignmentData;
use Modules\Finance\Domain\Events\BillingRunCommitted;
use Modules\Finance\Models\BillingRun;
use Modules\Finance\Models\LearnerFeeAssignment;
use Throwable;

/**
 * ACT-CommitBillingRun (Book B FIN-02 §3/BR-FIN-02-016/017). Raises
 * invoices — and, through them, posts one `FEE_BILLING` journal per
 * invoice — for every `approved` assignment in the run, via `FIN-03`'s
 * `IssueInvoicesForAssignmentAction`. Every invoice this commit
 * produces shares one `journal_batch_uuid`. A failure on one learner
 * is caught and reported, never aborting the rest (BR-FIN-02-016) —
 * `IssueInvoicesForAssignmentAction`'s own transaction is a savepoint
 * nested inside this action's, so a rolled-back failure doesn't touch
 * invoices/journals already posted for earlier learners in the same
 * commit. Because `execute()` refuses to run on anything but an
 * `approved` run, a run can only be committed once — that, not
 * per-assignment status, is what prevents re-issuing the same
 * invoices on a second call.
 */
final class CommitBillingRunAction extends Action
{
    public function __construct(
        private readonly IssueInvoicesForAssignmentAction $issueInvoices,
    ) {}

    public function execute(CommitBillingRunData $data): BillingRun
    {
        $run = BillingRun::findOrFail($data->billingRunId);

        if ($run->status !== 'approved') {
            throw new InvalidStateTransitionException(
                "A billing run can only be committed from [approved]; this one is [{$run->status}].",
                ['status' => $run->status],
            );
        }

        return $this->transaction(function () use ($run, $data): BillingRun {
            $run->update(['status' => 'committing']);
            $batchUuid = (string) Str::uuid();
            $failures = [];

            $assignments = LearnerFeeAssignment::query()
                ->where('billing_run_id', $run->id)
                ->where('status', 'approved')
                ->get();

            foreach ($assignments as $assignment) {
                try {
                    $this->issueInvoices->execute(new IssueInvoicesForAssignmentData(
                        assignmentId: $assignment->id,
                        issuedByUserId: $data->committedByUserId,
                        batchUuid: $batchUuid,
                        effectiveAt: $data->effectiveAt,
                    ));
                } catch (Throwable $e) {
                    $failures[] = ['student_id' => $assignment->student_id, 'assignment_id' => $assignment->id, 'error' => $e->getMessage()];
                }
            }

            $exceptionReport = $run->exception_report ?? [];
            $exceptionReport['commit_failures'] = $failures;

            $run->update([
                'status' => 'committed',
                'committed_at' => Carbon::now(),
                'journal_batch_uuid' => $batchUuid,
                'exception_report' => $exceptionReport,
            ]);

            event(new BillingRunCommitted($run));

            return $run;
        });
    }
}
