<?php

declare(strict_types=1);

namespace Modules\People\Domain\Actions;

use Illuminate\Support\Carbon;
use Modules\Core\Domain\Actions\Action;
use Modules\Core\Domain\Actions\Documents\AllocateNumberAction;
use Modules\Core\Domain\DataObjects\Documents\AllocateNumberData;
use Modules\People\Domain\DataObjects\CreateStudentData;
use Modules\People\Domain\DataObjects\DetectPossibleDuplicatesData;
use Modules\People\Domain\Events\LearnerEnrolled;
use Modules\People\Domain\Support\IdentifierHasher;
use Modules\People\Models\Student;
use Modules\People\Models\StudentEnrolment;

/**
 * ACT-CreateStudent (Book C PPL-01 §5/AC-PPL-01-001). Allocates the
 * admission number, creates the learner and their first enrolment, and
 * emits `LearnerEnrolled` — the event `FIN-02` will use to raise a
 * pro-rated first invoice once it exists.
 */
final class CreateStudentAction extends Action
{
    public function __construct(
        private readonly AllocateNumberAction $allocateNumber,
        private readonly DetectPossibleDuplicatesAction $detectDuplicates,
    ) {}

    public function execute(CreateStudentData $data): Student
    {
        if (! $data->skipDuplicateCheck) {
            $this->detectDuplicates->execute(new DetectPossibleDuplicatesData(
                schoolId: $data->schoolId,
                firstName: $data->firstName,
                lastName: $data->lastName,
                dateOfBirth: $data->dateOfBirth,
                nationalRegistrationNo: $data->nationalRegistrationNo,
                birthCertificateNo: $data->birthCertificateNo,
            ));
        }

        return $this->transaction(function () use ($data): Student {
            $number = $this->allocateNumber->execute(new AllocateNumberData(
                schoolId: $data->schoolId,
                documentType: 'admission',
                allocatedByUserId: $data->createdByUserId,
                academicYearId: $data->academicYearId,
            ));

            $enrolledOn = $data->enrolledOn ?? Carbon::now();

            $student = Student::create([
                'school_id' => $data->schoolId,
                'admission_number' => $number->formatted_number,
                'first_name' => $data->firstName,
                'middle_names' => $data->middleNames,
                'last_name' => $data->lastName,
                'preferred_name' => $data->preferredName,
                'date_of_birth' => $data->dateOfBirth->toDateString(),
                'gender' => $data->gender,
                'nationality' => $data->nationality,
                'national_registration_no' => $data->nationalRegistrationNo,
                'national_registration_no_hash' => IdentifierHasher::hash($data->nationalRegistrationNo),
                'birth_certificate_no' => $data->birthCertificateNo,
                'birth_certificate_no_hash' => IdentifierHasher::hash($data->birthCertificateNo),
                'enrolment_type' => $data->enrolmentType,
                'residency' => $data->residency,
                'section_id' => $data->sectionId,
                'grade_level_id' => $data->gradeLevelId,
                'class_id' => $data->classId,
                'house_id' => $data->houseId,
                'pathway' => $data->pathway,
                'entry_cohort_year' => $data->entryCohortYear,
                'status' => 'enrolled',
                'enrolled_on' => $enrolledOn->toDateString(),
                'created_by' => $data->createdByUserId,
            ]);

            StudentEnrolment::create([
                'school_id' => $data->schoolId,
                'student_id' => $student->id,
                'academic_year_id' => $data->academicYearId,
                'term_id' => $data->termId,
                'section_id' => $data->sectionId,
                'grade_level_id' => $data->gradeLevelId,
                'class_id' => $data->classId,
                'house_id' => $data->houseId,
                'enrolment_type' => $data->enrolmentType,
                'residency' => $data->residency,
                'pathway' => $data->pathway,
                'status' => 'active',
                'started_on' => $enrolledOn->toDateString(),
                'is_repeat' => false,
                'created_by' => $data->createdByUserId,
            ]);

            event(new LearnerEnrolled($student));

            return $student;
        });
    }
}
