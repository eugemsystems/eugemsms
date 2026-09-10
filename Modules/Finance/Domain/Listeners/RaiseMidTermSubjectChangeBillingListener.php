<?php

declare(strict_types=1);

namespace Modules\Finance\Domain\Listeners;

use Modules\Academic\Domain\Events\SubjectEnrolmentAdded;
use Modules\Academic\Domain\Events\SubjectEnrolmentDropped;
use Modules\Academic\Models\SubjectEnrolmentChange;
use Modules\Finance\Domain\Actions\BillMidTermSubjectChangeAction;
use Modules\Finance\Domain\DataObjects\BillMidTermSubjectChangeData;
use Throwable;

/**
 * Book B FIN-02 §4/BR-FIN-02-005 (AC-FIN-02-003/004). First Listener
 * class in the codebase — closes the `Event::listen()` gap
 * `BillMidTermSubjectChangeAction`'s own docblock used to name.
 * Registered directly in `FinanceServiceProvider::boot()`; no
 * `EventServiceProvider` exists in any module to autodiscover from.
 *
 * Lives in Finance (not Academic) so the dependency direction matches
 * `BillMidTermSubjectChangeAction`'s own: Finance depends on Academic's
 * events/models, never the reverse.
 *
 * A billing failure never rolls back the `ACA-02` enrolment change that
 * triggered it — the operation an event attaches to must not block on
 * it. The outcome is recorded on the already-append-only
 * `subject_enrolment_changes.billing_event_*` columns instead of
 * thrown, so an exception report can surface a failure for a human to
 * resolve. `billing_event_dispatched` also makes this idempotent: a
 * change already marked dispatched is not re-billed if the event were
 * ever replayed.
 */
final class RaiseMidTermSubjectChangeBillingListener
{
    public function __construct(private readonly BillMidTermSubjectChangeAction $action) {}

    public function handleAdded(SubjectEnrolmentAdded $event): void
    {
        $this->handle($event->enrolment->id, $event->change);
    }

    public function handleDropped(SubjectEnrolmentDropped $event): void
    {
        $this->handle($event->enrolment->id, $event->change);
    }

    private function handle(int $enrolmentId, SubjectEnrolmentChange $change): void
    {
        if ($change->billing_event_dispatched) {
            return;
        }

        try {
            $line = $this->action->execute(new BillMidTermSubjectChangeData(
                enrolmentId: $enrolmentId,
                changeId: $change->id,
                performedByUserId: $change->changed_by,
            ));

            $change->update([
                'billing_event_dispatched' => true,
                'billing_event_result' => $line === null ? 'skipped_no_assignment' : 'success',
                'billing_reference' => $line === null ? null : "fee_line:{$line->id}",
            ]);
        } catch (Throwable $e) {
            $change->update([
                'billing_event_dispatched' => true,
                'billing_event_result' => 'failed',
                'billing_reference' => substr($e->getMessage(), 0, 80),
            ]);
        }
    }
}
