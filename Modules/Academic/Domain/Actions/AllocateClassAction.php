<?php

declare(strict_types=1);

namespace Modules\Academic\Domain\Actions;

use Illuminate\Support\Collection;
use Modules\Academic\Domain\DataObjects\AllocateClassData;
use Modules\Academic\Domain\DataObjects\EnrolSubjectData;
use Modules\Academic\Domain\Events\ClassAllocationConfirmed;
use Modules\Academic\Models\ClassAllocation;
use Modules\Academic\Models\LearnerSubjectEnrolment;
use Modules\Academic\Models\LevelSubjectOffering;
use Modules\Core\Domain\Actions\Action;
use Modules\Core\Domain\Support\Settings\ScopeChain;
use Modules\Core\Domain\Support\Settings\SettingResolver;
use Modules\Core\Models\SchoolClass;
use Modules\Core\Models\Term;
use Modules\People\Models\Student;

/**
 * ACT-AllocateClass (Book D ACA-02 §2/§5/BR-ACA-02-004/014). Supersedes
 * any prior active allocation for the student in the term — mirrors
 * `CreateStaffPayStructureAction`'s/`CreateStatutoryConfigurationAction`'s
 * own supersede-not-overwrite pattern — then auto-enrols every
 * compulsory `level_subject_offerings` row for the student's grade
 * level not already actively enrolled, via `EnrolSubjectAction` itself
 * (never a second write path to `learner_subject_enrolments`).
 *
 * Self-confirmed (`confirmed_by` = the allocating user): the spec's
 * `draft`/`confirmed` split exists in the schema but no acceptance
 * criterion in this book names a distinct second-party confirmation
 * step, unlike `StatutoryConfiguration`'s explicit
 * `ConfirmStatutoryConfigurationAction`. A later pass can split this
 * if that requirement surfaces.
 */
final class AllocateClassAction extends Action
{
    public function __construct(
        private readonly EnrolSubjectAction $enrolSubject,
        private readonly SettingResolver $settings,
    ) {}

    public function execute(AllocateClassData $data): ClassAllocation
    {
        $student = Student::findOrFail($data->studentId);
        $class = SchoolClass::findOrFail($data->classId);
        $term = Term::findOrFail($data->termId);

        return $this->transaction(function () use ($student, $class, $term, $data): ClassAllocation {
            ClassAllocation::query()
                ->where('student_id', $student->id)
                ->where('term_id', $term->id)
                ->whereIn('status', ['draft', 'confirmed'])
                ->update(['status' => 'superseded', 'effective_to' => $data->effectiveFrom->toDateString()]);

            $allocation = ClassAllocation::create([
                'school_id' => $student->school_id,
                'academic_year_id' => $term->academic_year_id,
                'term_id' => $term->id,
                'student_id' => $student->id,
                'class_id' => $class->id,
                'allocation_type' => $data->allocationType,
                'effective_from' => $data->effectiveFrom->toDateString(),
                'status' => 'confirmed',
                'allocated_by' => $data->allocatedByUserId,
                'confirmed_by' => $data->allocatedByUserId,
                'notes' => $data->notes,
            ]);

            $this->autoEnrolCompulsorySubjects($student, $term, $data);

            event(new ClassAllocationConfirmed($allocation));

            return $allocation;
        });
    }

    private function autoEnrolCompulsorySubjects(Student $student, Term $term, AllocateClassData $data): void
    {
        $autoEnrol = (bool) $this->settings->get('curriculum.auto_enrol_compulsory', new ScopeChain(schoolId: $student->school_id));

        if (! $autoEnrol) {
            return;
        }

        $compulsorySubjectIds = LevelSubjectOffering::withoutGlobalScopes()
            ->where('academic_year_id', $term->academic_year_id)
            ->where('grade_level_id', $student->grade_level_id)
            ->where('is_compulsory', true)
            ->where('is_available', true)
            ->pluck('subject_id');

        $alreadyEnrolled = LearnerSubjectEnrolment::query()
            ->where('student_id', $student->id)
            ->where('term_id', $term->id)
            ->where('status', 'active')
            ->pluck('subject_id');

        /** @var Collection<int, int> $toEnrol */
        $toEnrol = $compulsorySubjectIds->diff($alreadyEnrolled);

        foreach ($toEnrol as $subjectId) {
            $this->enrolSubject->execute(new EnrolSubjectData(
                studentId: $student->id,
                subjectId: $subjectId,
                termId: $term->id,
                addedByUserId: $data->allocatedByUserId,
                enrolmentReason: 'compulsory',
                effectiveFrom: $data->effectiveFrom,
                acknowledgeWarnings: true,
            ));
        }
    }
}
