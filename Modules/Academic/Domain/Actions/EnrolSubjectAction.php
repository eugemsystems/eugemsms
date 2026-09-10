<?php

declare(strict_types=1);

namespace Modules\Academic\Domain\Actions;

use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Modules\Academic\Domain\DataObjects\EnrolSubjectData;
use Modules\Academic\Domain\Events\SubjectEnrolmentAdded;
use Modules\Academic\Domain\Exceptions\SubjectBackdateLimitExceededException;
use Modules\Academic\Domain\Exceptions\SubjectSelectionBlockedException;
use Modules\Academic\Domain\Exceptions\SubjectSelectionRequiresAcknowledgementException;
use Modules\Academic\Domain\Support\SubjectChangeCutoffPolicy;
use Modules\Academic\Domain\Support\SubjectSelectionRuleEngine;
use Modules\Academic\Domain\Support\TermProrationCalculator;
use Modules\Academic\Models\LearnerSubjectEnrolment;
use Modules\Academic\Models\Subject;
use Modules\Academic\Models\SubjectEnrolmentChange;
use Modules\Core\Domain\Actions\Action;
use Modules\Core\Domain\Support\Settings\ScopeChain;
use Modules\Core\Domain\Support\Settings\SettingResolver;
use Modules\Core\Models\Term;
use Modules\People\Models\Student;

/**
 * ACT-EnrolSubject (Book D ACA-02 §4/§5, steps 1-6 of the add/drop
 * billing flow). Steps 7-14 — raising the per-subject charge — are
 * `FIN-02`'s `RaiseMidTermSubjectChangeBillingListener`'s own scope,
 * bound to the `SubjectEnrolmentAdded` event this action emits; this
 * action's own job ends at creating the enrolment, snapshotting the
 * proration inputs, and emitting that event.
 */
final class EnrolSubjectAction extends Action
{
    public function __construct(
        private readonly SubjectSelectionRuleEngine $ruleEngine,
        private readonly TermProrationCalculator $proration,
        private readonly SubjectChangeCutoffPolicy $cutoff,
        private readonly SettingResolver $settings,
    ) {}

    public function execute(EnrolSubjectData $data): LearnerSubjectEnrolment
    {
        $student = Student::findOrFail($data->studentId);
        $subject = Subject::findOrFail($data->subjectId);
        $term = Term::findOrFail($data->termId);

        $this->cutoff->assertWithinCutoff($term, $student->school_id);

        $effectiveFrom = $data->effectiveFrom ?? Carbon::now();

        $this->assertBackdateAllowed($student->school_id, $effectiveFrom);

        $proposedSubjectIds = LearnerSubjectEnrolment::query()
            ->where('student_id', $student->id)
            ->where('term_id', $term->id)
            ->where('status', 'active')
            ->pluck('subject_id')
            ->push($subject->id)
            ->unique();

        $result = $this->ruleEngine->validate(
            $proposedSubjectIds,
            $subject->framework_id,
            $student->grade_level_id,
            $student->pathway,
            $student->school_id,
            $term->academic_year_id,
            $student->id,
        );

        if (! $result->isValid) {
            throw SubjectSelectionBlockedException::forViolations($result->blocks);
        }

        if ($result->hasWarnings() && ! $data->acknowledgeWarnings) {
            throw SubjectSelectionRequiresAcknowledgementException::forViolations($result->warnings);
        }

        [$remaining, $total] = array_values($this->proration->remainingAndTotal($term, $effectiveFrom));

        return $this->transaction(function () use ($student, $subject, $term, $data, $effectiveFrom, $remaining, $total): LearnerSubjectEnrolment {
            $enrolment = LearnerSubjectEnrolment::create([
                'school_id' => $student->school_id,
                'academic_year_id' => $term->academic_year_id,
                'term_id' => $term->id,
                'student_id' => $student->id,
                'subject_id' => $subject->id,
                'class_id' => $data->classId,
                'subject_group_id' => $subject->subject_group_id,
                'enrolment_reason' => $data->enrolmentReason,
                'status' => 'active',
                'effective_from' => $effectiveFrom->toDateString(),
                'is_billable' => true,
                'billing_status' => 'pending',
                'added_by' => $data->addedByUserId,
                'added_at' => Carbon::now(),
            ]);

            $change = SubjectEnrolmentChange::create([
                'school_id' => $student->school_id,
                'student_id' => $student->id,
                'term_id' => $term->id,
                'subject_id' => $subject->id,
                'change_type' => 'added',
                'effective_from' => $effectiveFrom->toDateString(),
                'teaching_days_remaining' => $remaining,
                'term_teaching_days' => $total,
                'proration_factor' => $total > 0 ? round($remaining / $total, 6) : 0,
                'reason' => $data->reason,
                'changed_by' => $data->addedByUserId,
                'changed_at' => Carbon::now(),
            ]);

            event(new SubjectEnrolmentAdded($enrolment, $change));

            return $enrolment;
        });
    }

    private function assertBackdateAllowed(int $schoolId, CarbonInterface $effectiveFrom): void
    {
        if ($effectiveFrom->startOfDay()->greaterThanOrEqualTo(Carbon::now()->startOfDay())) {
            return;
        }

        $allowBackdating = (bool) $this->settings->get('academic.allow_backdated_enrolment', new ScopeChain(schoolId: $schoolId));

        if (! $allowBackdating) {
            throw SubjectBackdateLimitExceededException::backdatingDisabled();
        }

        $limitDays = (int) $this->settings->get('academic.backdate_limit_days', new ScopeChain(schoolId: $schoolId));
        $daysInPast = (int) Carbon::now()->startOfDay()->diffInDays($effectiveFrom->copy()->startOfDay(), absolute: true);

        if ($daysInPast > $limitDays) {
            throw SubjectBackdateLimitExceededException::forDays($daysInPast, $limitDays);
        }
    }
}
