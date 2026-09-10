<?php

declare(strict_types=1);

namespace Modules\People\Domain\Actions;

use Illuminate\Support\Carbon;
use Modules\Core\Domain\Actions\Action;
use Modules\Core\Domain\Actions\Documents\AllocateNumberAction;
use Modules\Core\Domain\DataObjects\Documents\AllocateNumberData;
use Modules\People\Domain\DataObjects\SubmitApplicationData;
use Modules\People\Domain\Events\ApplicationSubmitted;
use Modules\People\Domain\Support\IdentifierHasher;
use Modules\People\Models\Application;
use Modules\People\Models\ApplicationGuardian;
use Modules\People\Models\Intake;

/**
 * ACT-SubmitApplication (Book C PPL-02 §4/BR-PPL-02-002/003). The
 * application number is allocated here, on submission — never on the
 * draft this is built from (this pass has no separate draft-save
 * step; a caller who wants one just holds the DTO client-side until
 * ready to submit). A configured application fee holds the row at
 * `fee_pending`, invisible to the review queue, until
 * `PayApplicationFeeAction` clears it.
 *
 * BR-PPL-02-001's public, unauthenticated form still needs a real
 * `createdByUserId` for `AllocateNumberAction`'s `allocated_by` — a
 * public-facing controller is responsible for supplying a pre-seeded
 * system user id (none exists yet; no public controller is built in
 * this pass either).
 */
final class SubmitApplicationAction extends Action
{
    public function __construct(
        private readonly AllocateNumberAction $allocateNumber,
    ) {}

    public function execute(SubmitApplicationData $data): Application
    {
        $intake = Intake::findOrFail($data->intakeId);

        return $this->transaction(function () use ($intake, $data): Application {
            $number = $this->allocateNumber->execute(new AllocateNumberData(
                schoolId: $data->schoolId,
                documentType: 'application',
                allocatedByUserId: $data->createdByUserId,
                academicYearId: $intake->academic_year_id,
            ));

            $application = Application::create([
                'school_id' => $data->schoolId,
                'intake_id' => $intake->id,
                'application_number' => $number->formatted_number,
                'first_name' => $data->firstName,
                'middle_names' => $data->middleNames,
                'last_name' => $data->lastName,
                'date_of_birth' => $data->dateOfBirth->toDateString(),
                'gender' => $data->gender,
                'nationality' => $data->nationality,
                'national_registration_no' => $data->nationalRegistrationNo,
                'national_registration_no_hash' => IdentifierHasher::hash($data->nationalRegistrationNo),
                'birth_certificate_no' => $data->birthCertificateNo,
                'birth_certificate_no_hash' => IdentifierHasher::hash($data->birthCertificateNo),
                'requested_grade_level_id' => $data->requestedGradeLevelId,
                'requested_enrolment_type' => $data->requestedEnrolmentType,
                'requested_residency' => $data->requestedResidency,
                'requested_pathway' => $data->requestedPathway,
                'requested_subjects' => $data->requestedSubjectIds,
                'has_sibling_at_school' => $data->hasSiblingAtSchool,
                'sibling_student_id' => $data->siblingStudentId,
                'guardian_is_alumnus' => $data->guardianIsAlumnus,
                'guardian_is_staff' => $data->guardianIsStaff,
                'status' => $intake->application_fee_minor !== null ? 'fee_pending' : 'submitted',
                'submitted_at' => Carbon::now(),
                'created_by' => $data->createdByUserId,
            ]);

            foreach ($data->guardians as $guardian) {
                ApplicationGuardian::create([
                    'application_id' => $application->id,
                    'relationship' => $guardian['relationship'],
                    'title' => $guardian['title'] ?? null,
                    'first_name' => $guardian['first_name'] ?? null,
                    'last_name' => $guardian['last_name'] ?? null,
                    'organisation_name' => $guardian['organisation_name'] ?? null,
                    'primary_phone' => $guardian['primary_phone'] ?? null,
                    'email' => $guardian['email'] ?? null,
                    'is_primary_contact' => $guardian['is_primary_contact'] ?? false,
                    'is_fee_responsible' => $guardian['is_fee_responsible'] ?? false,
                    'existing_guardian_id' => $guardian['existing_guardian_id'] ?? null,
                ]);
            }

            event(new ApplicationSubmitted($application));

            return $application->load('guardians');
        });
    }
}
