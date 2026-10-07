<?php

declare(strict_types=1);

namespace Modules\Academic\Domain\Actions;

use Illuminate\Support\Collection;
use Modules\Academic\Domain\DataObjects\BulkEnrolmentOutcome;
use Modules\Academic\Domain\DataObjects\BulkEnrolSubjectsData;
use Modules\Academic\Domain\DataObjects\EnrolSubjectData;
use Modules\Core\Domain\Actions\Action;
use Throwable;

/**
 * ACT-BulkEnrolSubjects (Book D ACA-02 §5/BR-ACA-02-017,
 * `Enrolment\Bulk`). Runs the real `EnrolSubjectAction` once per
 * learner — the same rule-engine/proration/cutoff checks a single
 * enrolment gets, never a second, lighter validation path — and
 * reports every outcome rather than a single pass/fail. Each call is
 * its own transaction (inherited from `EnrolSubjectAction` itself), so
 * one learner's block or exception never touches another's already-
 * committed row (BR-ACA-02-017: "one failure does not abort the
 * batch"). `acknowledgeWarnings` is one shared flag for the whole
 * batch, not per learner — re-prompting mid-batch would break the
 * single-pass semantic this screen's own "run and see the report"
 * shape depends on; a learner whose only problem is a `warn`-severity
 * rule is reported as not-enrolled with that rule's message when the
 * flag is off, same as the single-learner screen without its
 * "acknowledge and retry" step. Catches `Throwable`, not just
 * `DomainException` — BR-ACA-02-017's "one failure does not abort the
 * batch" has to hold even for a plain unique-constraint violation
 * (e.g. two learners who both already hold the subject on the same
 * `effective_from`), which `EnrolSubjectAction` lets reach the
 * database as a raw `QueryException` rather than a `DomainException`.
 */
final class BulkEnrolSubjectsAction extends Action
{
    protected bool $transactional = false;

    public function __construct(
        private readonly EnrolSubjectAction $enrolSubject,
    ) {}

    /**
     * @return Collection<int, BulkEnrolmentOutcome>
     */
    public function execute(BulkEnrolSubjectsData $data): Collection
    {
        $outcomes = collect();

        foreach ($data->studentIds as $studentId) {
            try {
                $this->enrolSubject->execute(new EnrolSubjectData(
                    studentId: $studentId,
                    subjectId: $data->subjectId,
                    termId: $data->termId,
                    addedByUserId: $data->addedByUserId,
                    enrolmentReason: $data->enrolmentReason,
                    effectiveFrom: $data->effectiveFrom,
                    acknowledgeWarnings: $data->acknowledgeWarnings,
                    reason: $data->reason,
                ));

                $outcomes->push(new BulkEnrolmentOutcome($studentId, enrolled: true));
            } catch (Throwable $e) {
                $outcomes->push(new BulkEnrolmentOutcome($studentId, enrolled: false, message: $e->getMessage()));
            }
        }

        return $outcomes;
    }
}
