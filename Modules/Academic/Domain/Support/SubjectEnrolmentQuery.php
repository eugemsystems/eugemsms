<?php

declare(strict_types=1);

namespace Modules\Academic\Domain\Support;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Modules\Academic\Models\LearnerSubjectEnrolment;
use Modules\Academic\Models\SubjectEnrolmentChange;
use Modules\Core\Models\Term;
use Modules\People\Models\Student;

/**
 * Book D ACA-02 §3 ⭐. The contract `FIN-02` (Book B) was specified
 * against, per `BR-FIN-02-004`: `PER_SUBJECT` quantity is derived from
 * `learner_subject_enrolments` on the billing date. There is no
 * separate "billable subject count" field, and no other class computes
 * this number — `BR-ACA-02-001`.
 */
final class SubjectEnrolmentQuery
{
    public function billableCountOn(Student $student, Term $term, CarbonInterface $on): int
    {
        return $this->billableQuery($student, $term, $on)->count();
    }

    /**
     * @return Collection<int, LearnerSubjectEnrolment>
     */
    public function billableSubjectsOn(Student $student, Term $term, CarbonInterface $on): Collection
    {
        return $this->billableQuery($student, $term, $on)->with('subject', 'subjectGroup')->get();
    }

    /**
     * @return Collection<int, SubjectEnrolmentChange>
     */
    public function changesInTerm(Student $student, Term $term): Collection
    {
        return SubjectEnrolmentChange::query()
            ->where('student_id', $student->id)
            ->where('term_id', $term->id)
            ->orderBy('effective_from')
            ->get();
    }

    /**
     * @return Builder<LearnerSubjectEnrolment>
     */
    private function billableQuery(Student $student, Term $term, CarbonInterface $on): Builder
    {
        return LearnerSubjectEnrolment::query()
            ->where('student_id', $student->id)
            ->where('term_id', $term->id)
            ->where('is_billable', true)
            ->where('effective_from', '<=', $on)
            ->where(fn ($q) => $q->whereNull('effective_to')->orWhere('effective_to', '>', $on));
    }
}
