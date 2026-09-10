<?php

declare(strict_types=1);

namespace Modules\People\Domain\Actions;

use Illuminate\Support\Carbon;
use InvalidArgumentException;
use Modules\Academic\Domain\Actions\EnrolSubjectAction;
use Modules\Academic\Domain\DataObjects\EnrolSubjectData;
use Modules\Core\Domain\Actions\Action;
use Modules\Core\Domain\Exceptions\InvalidStateTransitionException;
use Modules\Core\Domain\Support\Currency;
use Modules\Core\Domain\Support\Money;
use Modules\Core\Models\GradeLevel;
use Modules\Finance\Domain\Actions\PostJournalAction;
use Modules\Finance\Domain\DataObjects\JournalLineData;
use Modules\Finance\Domain\DataObjects\PostJournalData;
use Modules\Finance\Models\Journal;
use Modules\Finance\Models\Receipt;
use Modules\People\Domain\Actions\CreateFeeLiabilityAction as PplCreateFeeLiabilityAction;
use Modules\People\Domain\Actions\CreateGuardianAction as PplCreateGuardianAction;
use Modules\People\Domain\Actions\LinkGuardianToStudentAction as PplLinkGuardianToStudentAction;
use Modules\People\Domain\DataObjects\ConvertApplicationToStudentData;
use Modules\People\Domain\DataObjects\CreateFeeLiabilityData;
use Modules\People\Domain\DataObjects\CreateGuardianData;
use Modules\People\Domain\DataObjects\CreateStudentData;
use Modules\People\Domain\DataObjects\LinkGuardianToStudentData;
use Modules\People\Domain\Exceptions\IntakeCapacityExceededException;
use Modules\People\Models\Application;
use Modules\People\Models\ApplicationGuardian;
use Modules\People\Models\Guardian;
use Modules\People\Models\Student;

/**
 * ACT-ConvertApplicationToStudent (Book C PPL-02 §3 ⭐/BR-PPL-02-009
 * (AC-PPL-02-004/005/007)). "Zero re-keying" — every applicant field
 * mirrors its `Student` counterpart, so this one transaction creates
 * the learner, links or creates each guardian, raises fee liabilities,
 * converts the acceptance deposit into a real learner credit, and
 * (when requested) enrols the part-time subjects, with no partial
 * state possible if any step fails.
 *
 * Guardian matching is by exact `primary_phone` string only — no
 * phone normalisation utility exists yet (BR-PPL-03-013 names E.164
 * normalisation as the eventual target) and neither `guardians` nor
 * `application_guardians` carries a national-registration hash in
 * this pass, matching `guardians`' own already-recorded PII scope
 * cut. `application_documents`/`previous_results` copying is skipped
 * entirely — no `student_documents`/`student_prior_results` tables
 * exist to copy into.
 */
final class ConvertApplicationToStudentAction extends Action
{
    public function __construct(
        private readonly CreateStudentAction $createStudent,
        private readonly PplCreateGuardianAction $createGuardian,
        private readonly PplLinkGuardianToStudentAction $linkGuardian,
        private readonly PplCreateFeeLiabilityAction $createFeeLiability,
        private readonly EnrolSubjectAction $enrolSubject,
        private readonly PostJournalAction $postJournal,
    ) {}

    public function execute(ConvertApplicationToStudentData $data): Student
    {
        $application = Application::with('guardians')->findOrFail($data->applicationId);

        if ($application->status !== 'deposit_paid') {
            throw new InvalidStateTransitionException(
                "An application can only be converted from [deposit_paid]; this one is [{$application->status}].",
                ['status' => $application->status],
            );
        }

        $intake = $application->intake;

        // Deliberately not `! $intake->hasCapacity()` (places_accepted <
        // target_places): places_accepted already includes THIS
        // application's own deposit-time reservation, so a `<` comparison
        // would block converting the very applicant who exactly fills the
        // last place. `>` only refuses once places_accepted has already
        // overrun target_places — i.e. some other applicant's deposit
        // pushed the intake over, not this one's.
        if ($intake->places_accepted > $intake->target_places && ! $data->overrideCapacity) {
            throw IntakeCapacityExceededException::forIntake($intake->id);
        }

        if ($data->overrideCapacity && ($data->overrideReason === null || trim($data->overrideReason) === '')) {
            throw new InvalidArgumentException('overrideReason is required when overriding intake capacity (BR-PPL-02-008).');
        }

        return $this->transaction(function () use ($application, $data): Student {
            $sectionId = GradeLevel::findOrFail($application->requested_grade_level_id)->section_id;

            $student = $this->createStudent->execute(new CreateStudentData(
                schoolId: $application->school_id,
                academicYearId: $application->intake->academic_year_id,
                termId: $data->termId,
                firstName: $application->first_name,
                lastName: $application->last_name,
                dateOfBirth: $application->date_of_birth,
                gender: $application->gender,
                enrolmentType: $application->requested_enrolment_type,
                residency: $application->requested_residency,
                sectionId: $sectionId,
                gradeLevelId: $application->requested_grade_level_id,
                entryCohortYear: (int) Carbon::now()->year,
                createdByUserId: $data->convertedByUserId,
                middleNames: $application->middle_names,
                nationality: $application->nationality,
                nationalRegistrationNo: $application->national_registration_no,
                birthCertificateNo: $application->birth_certificate_no,
                pathway: $application->requested_pathway,
                skipDuplicateCheck: true,
            ));

            foreach ($application->guardians as $applicationGuardian) {
                $guardian = $this->matchOrCreateGuardian($application->school_id, $applicationGuardian, $data->convertedByUserId);

                $this->linkGuardian->execute(new LinkGuardianToStudentData(
                    studentId: $student->id,
                    guardianId: $guardian->id,
                    relationship: $applicationGuardian->relationship,
                    createdByUserId: $data->convertedByUserId,
                    isPrimaryContact: $applicationGuardian->is_primary_contact,
                    isFeeResponsible: $applicationGuardian->is_fee_responsible,
                ));

                if ($applicationGuardian->is_fee_responsible) {
                    $this->createFeeLiability->execute(new CreateFeeLiabilityData(
                        schoolId: $application->school_id,
                        studentId: $student->id,
                        guardianId: $guardian->id,
                        shareType: 'percentage',
                        createdByUserId: $data->convertedByUserId,
                        sharePercent: '100.00',
                    ));
                }
            }

            $this->convertDeposit($application, $student, $data);

            foreach ($application->requested_subjects ?? [] as $subjectId) {
                $this->enrolSubject->execute(new EnrolSubjectData(
                    studentId: $student->id,
                    subjectId: $subjectId,
                    termId: $data->termId,
                    addedByUserId: $data->convertedByUserId,
                    enrolmentReason: 'elective',
                ));
            }

            $application->update([
                'status' => 'enrolled',
                'student_id' => $student->id,
                'converted_at' => Carbon::now(),
            ]);

            return $student;
        });
    }

    private function matchOrCreateGuardian(int $schoolId, ApplicationGuardian $applicationGuardian, int $createdByUserId): Guardian
    {
        if ($applicationGuardian->existing_guardian_id !== null) {
            return Guardian::findOrFail($applicationGuardian->existing_guardian_id);
        }

        if ($applicationGuardian->primary_phone !== null) {
            $matched = Guardian::query()
                ->where('school_id', $schoolId)
                ->where('primary_phone', $applicationGuardian->primary_phone)
                ->first();

            if ($matched !== null) {
                return $matched;
            }
        }

        return $this->createGuardian->execute(new CreateGuardianData(
            schoolId: $schoolId,
            guardianType: $applicationGuardian->organisation_name !== null ? 'organisation' : 'individual',
            createdByUserId: $createdByUserId,
            title: $applicationGuardian->title,
            firstName: $applicationGuardian->first_name,
            lastName: $applicationGuardian->last_name,
            organisationName: $applicationGuardian->organisation_name,
            primaryPhone: $applicationGuardian->primary_phone,
            email: $applicationGuardian->email,
        ));
    }

    private function convertDeposit(Application $application, Student $student, ConvertApplicationToStudentData $data): void
    {
        if ($application->deposit_receipt_id === null) {
            return;
        }

        $depositReceipt = Receipt::findOrFail($application->deposit_receipt_id);
        $depositJournal = Journal::with('lines')->find($depositReceipt->journal_id);

        if ($depositJournal === null) {
            return;
        }

        $depositLine = $depositJournal->lines->firstWhere('direction', 'CR');

        if ($depositLine === null) {
            return;
        }

        $amount = Money::of((int) $depositLine->amount_minor, Currency::from($depositLine->currency));

        $this->postJournal->execute(new PostJournalData(
            schoolId: $application->school_id,
            academicYearId: $application->intake->academic_year_id,
            termId: $data->termId,
            journalType: 'DEPOSIT_CONVERSION',
            narration: "Acceptance deposit converted to credit — application {$application->application_number}",
            lines: [
                new JournalLineData(accountId: (int) $depositLine->account_id, direction: 'DR', amount: $amount, narration: 'Deposit converted'),
                new JournalLineData(
                    accountId: $data->creditBalanceAccountId,
                    direction: 'CR',
                    amount: $amount,
                    subledgerType: 'student',
                    subledgerId: $student->id,
                    narration: 'Acceptance deposit converted to learner credit',
                ),
            ],
            effectiveAt: Carbon::now(),
            postedByUserId: $data->convertedByUserId,
            sourceType: 'application',
            sourceId: $application->id,
        ));
    }
}
