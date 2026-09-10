<?php

use App\Models\User;
use Illuminate\Support\Facades\Event;
use Modules\Core\Domain\Actions\Documents\CreateNumberingSeriesAction;
use Modules\Core\Domain\DataObjects\Documents\CreateNumberingSeriesData;
use Modules\Core\Domain\Exceptions\InvalidStateTransitionException;
use Modules\Core\Domain\Support\SchoolContext;
use Modules\Core\Models\AcademicYear;
use Modules\Core\Models\GradeLevel;
use Modules\Core\Models\School;
use Modules\Core\Models\SchoolSection;
use Modules\Core\Models\Term;
use Modules\People\Domain\Actions\ChangeBillingAttributeAction;
use Modules\People\Domain\Actions\ChangeStudentStatusAction;
use Modules\People\Domain\Actions\CreateStudentAction;
use Modules\People\Domain\Actions\DetectPossibleDuplicatesAction;
use Modules\People\Domain\Actions\ReadmitStudentAction;
use Modules\People\Domain\Actions\UpdateStudentProfileAction;
use Modules\People\Domain\Actions\WithdrawStudentAction;
use Modules\People\Domain\DataObjects\ChangeBillingAttributeData;
use Modules\People\Domain\DataObjects\ChangeStudentStatusData;
use Modules\People\Domain\DataObjects\CreateStudentData;
use Modules\People\Domain\DataObjects\DetectPossibleDuplicatesData;
use Modules\People\Domain\DataObjects\ReadmitStudentData;
use Modules\People\Domain\DataObjects\UpdateStudentProfileData;
use Modules\People\Domain\DataObjects\WithdrawStudentData;
use Modules\People\Domain\Events\LearnerEnrolled;
use Modules\People\Domain\Events\LearnerResidencyChanged;
use Modules\People\Domain\Events\LearnerWithdrawn;
use Modules\People\Domain\Events\PossibleDuplicateLearnerDetected;
use Modules\People\Domain\Exceptions\BackdateLimitExceededException;
use Modules\People\Domain\Exceptions\InvalidBillingAttributeException;
use Modules\People\Models\Student;

/**
 * @return array{school: School, year: AcademicYear, term: Term, section: SchoolSection, gradeLevel: GradeLevel, user: User}
 */
function studentFixture(): array
{
    $school = School::factory()->create();
    SchoolContext::set($school);
    $year = AcademicYear::factory()->for($school)->create();
    $term = Term::factory()->for($school)->for($year, 'academicYear')->create();
    $section = SchoolSection::factory()->for($school)->create();
    $gradeLevel = GradeLevel::factory()->for($school)->for($section, 'section')->create();

    app(CreateNumberingSeriesAction::class)->execute(new CreateNumberingSeriesData(
        schoolId: $school->id, documentType: 'admission', pattern: '{SCHOOL}/{YEAR}/{SEQ:4}', academicYearId: $year->id,
    ));

    return ['school' => $school, 'year' => $year, 'term' => $term, 'section' => $section, 'gradeLevel' => $gradeLevel, 'user' => User::factory()->create()];
}

function createStudentData(array $f, array $overrides = []): CreateStudentData
{
    return new CreateStudentData(
        schoolId: $f['school']->id,
        academicYearId: $f['year']->id,
        termId: $f['term']->id,
        firstName: $overrides['firstName'] ?? 'Tinashe',
        lastName: $overrides['lastName'] ?? 'Moyo',
        dateOfBirth: $overrides['dateOfBirth'] ?? now()->subYears(14),
        gender: 'male',
        enrolmentType: $overrides['enrolmentType'] ?? 'FULL_TIME',
        residency: $overrides['residency'] ?? 'BOARDER',
        sectionId: $f['section']->id,
        gradeLevelId: $f['gradeLevel']->id,
        entryCohortYear: (int) now()->year,
        createdByUserId: $f['user']->id,
        nationalRegistrationNo: $overrides['nationalRegistrationNo'] ?? null,
        skipDuplicateCheck: $overrides['skipDuplicateCheck'] ?? false,
    );
}

it('creates a student with a gapless admission number and a first enrolment (AC-PPL-01-001)', function (): void {
    $f = studentFixture();

    Event::fake([LearnerEnrolled::class]);

    $student = app(CreateStudentAction::class)->execute(createStudentData($f));

    expect($student->admission_number)->toStartWith($f['school']->code)
        ->and($student->status)->toBe('enrolled')
        ->and($student->enrolments()->count())->toBe(1);

    Event::assertDispatched(LearnerEnrolled::class);
});

it('refuses to update enrolment_type directly on the model (BR-PPL-01-004/AC-PPL-01-004)', function (): void {
    $f = studentFixture();
    $student = app(CreateStudentAction::class)->execute(createStudentData($f));

    $student->update(['residency' => 'DAY']);
})->throws(InvalidStateTransitionException::class);

it('refuses to update admission_number once assigned (BR-PPL-01-001)', function (): void {
    $f = studentFixture();
    $student = app(CreateStudentAction::class)->execute(createStudentData($f));

    $student->update(['admission_number' => 'HACKED/0001']);
})->throws(InvalidStateTransitionException::class);

it('changes a billing attribute through the single authorised path, writing an append-only history row (BR-PPL-01-004)', function (): void {
    $f = studentFixture();
    $student = app(CreateStudentAction::class)->execute(createStudentData($f, ['residency' => 'BOARDER']));

    Event::fake([LearnerResidencyChanged::class]);

    $change = app(ChangeBillingAttributeAction::class)->execute(new ChangeBillingAttributeData(
        studentId: $student->id,
        attribute: 'residency',
        newValue: 'DAY',
        effectiveFrom: now(),
        changedByUserId: $f['user']->id,
        reason: 'Parent moved closer to school.',
    ));

    expect($change->old_value)->toBe('BOARDER')
        ->and($change->new_value)->toBe('DAY')
        ->and($change->triggers_rebilling)->toBeTrue()
        ->and($student->fresh()->residency)->toBe('DAY');

    Event::assertDispatched(LearnerResidencyChanged::class);
});

it('separates effective_from (billing truth) from changed_at (entry date) (AC-PPL-01-003)', function (): void {
    $f = studentFixture();
    $student = app(CreateStudentAction::class)->execute(createStudentData($f));

    $backdated = now()->subDays(8);

    $change = app(ChangeBillingAttributeAction::class)->execute(new ChangeBillingAttributeData(
        studentId: $student->id,
        attribute: 'residency',
        newValue: 'DAY',
        effectiveFrom: $backdated,
        changedByUserId: $f['user']->id,
    ));

    expect($change->effective_from->toDateString())->toBe($backdated->toDateString())
        ->and($change->changed_at->toDateString())->toBe(now()->toDateString());
});

it('refuses a billing attribute name outside the six-attribute contract', function (): void {
    $f = studentFixture();
    $student = app(CreateStudentAction::class)->execute(createStudentData($f));

    app(ChangeBillingAttributeAction::class)->execute(new ChangeBillingAttributeData(
        studentId: $student->id, attribute: 'nationality', newValue: 'ZA', effectiveFrom: now(), changedByUserId: $f['user']->id,
    ));
})->throws(InvalidBillingAttributeException::class);

it('refuses a backdated change beyond the configured limit (BR-PPL-01-005)', function (): void {
    $f = studentFixture();
    $student = app(CreateStudentAction::class)->execute(createStudentData($f));

    app(ChangeBillingAttributeAction::class)->execute(new ChangeBillingAttributeData(
        studentId: $student->id, attribute: 'residency', newValue: 'DAY', effectiveFrom: now()->subDays(90), changedByUserId: $f['user']->id,
    ));
})->throws(BackdateLimitExceededException::class);

it('flags a possible duplicate on matching national registration number, without merging (BR-PPL-01-009/AC-PPL-01-005)', function (): void {
    $f = studentFixture();
    Event::fake([PossibleDuplicateLearnerDetected::class]);

    app(CreateStudentAction::class)->execute(createStudentData($f, ['nationalRegistrationNo' => '63-123456A12', 'skipDuplicateCheck' => true]));

    $duplicates = app(DetectPossibleDuplicatesAction::class)->execute(new DetectPossibleDuplicatesData(
        schoolId: $f['school']->id,
        firstName: 'Different',
        lastName: 'Name',
        dateOfBirth: now()->subYears(20),
        nationalRegistrationNo: '63 123456 A 12',
    ));

    expect($duplicates)->toHaveCount(1)
        ->and($duplicates[0]->matchedOn)->toBe('national_registration_no')
        ->and(Student::count())->toBe(1);

    Event::assertDispatched(PossibleDuplicateLearnerDetected::class);
});

it('changes status through the state machine and refuses an illegal transition (BR-PPL-01-011)', function (): void {
    $f = studentFixture();
    $student = app(CreateStudentAction::class)->execute(createStudentData($f));

    $activated = app(ChangeStudentStatusAction::class)->execute(new ChangeStudentStatusData(
        studentId: $student->id, newStatus: 'active', changedByUserId: $f['user']->id,
    ));
    expect($activated->status)->toBe('active');

    app(ChangeStudentStatusAction::class)->execute(new ChangeStudentStatusData(
        studentId: $student->id, newStatus: 'graduated', changedByUserId: $f['user']->id,
    ));

    app(ChangeStudentStatusAction::class)->execute(new ChangeStudentStatusData(
        studentId: $student->id, newStatus: 'active', changedByUserId: $f['user']->id,
    ));
})->throws(InvalidStateTransitionException::class);

it('withdraws a student, emitting LearnerWithdrawn with the exit date (BR-PPL-01-013/AC-PPL-01-007)', function (): void {
    $f = studentFixture();
    $student = app(CreateStudentAction::class)->execute(createStudentData($f));
    app(ChangeStudentStatusAction::class)->execute(new ChangeStudentStatusData($student->id, 'active', $f['user']->id));

    Event::fake([LearnerWithdrawn::class]);

    $exitDate = now();
    $withdrawn = app(WithdrawStudentAction::class)->execute(new WithdrawStudentData(
        studentId: $student->id, exitedOn: $exitDate, withdrawnByUserId: $f['user']->id, reason: 'Family relocated.',
    ));

    expect($withdrawn->status)->toBe('withdrawn')
        ->and($withdrawn->exited_on->toDateString())->toBe($exitDate->toDateString());

    Event::assertDispatched(LearnerWithdrawn::class);
});

it('readmits a withdrawn student, retaining the original admission number and history (BR-PPL-01-002/AC-PPL-01-008)', function (): void {
    $f = studentFixture();
    $student = app(CreateStudentAction::class)->execute(createStudentData($f));
    app(ChangeStudentStatusAction::class)->execute(new ChangeStudentStatusData($student->id, 'active', $f['user']->id));
    $originalAdmissionNumber = $student->admission_number;

    app(WithdrawStudentAction::class)->execute(new WithdrawStudentData($student->id, now(), $f['user']->id));

    $newYear = AcademicYear::factory()->for($f['school'])->create();
    $newTerm = Term::factory()->for($f['school'])->for($newYear, 'academicYear')->create();

    $readmitted = app(ReadmitStudentAction::class)->execute(new ReadmitStudentData(
        studentId: $student->id, academicYearId: $newYear->id, termId: $newTerm->id, readmittedByUserId: $f['user']->id,
    ));

    expect($readmitted->admission_number)->toBe($originalAdmissionNumber)
        ->and($readmitted->status)->toBe('active')
        ->and($readmitted->exited_on)->toBeNull()
        ->and($readmitted->enrolments()->count())->toBe(2);
});

it('updates non-billing profile fields without touching billing attributes', function (): void {
    $f = studentFixture();
    $student = app(CreateStudentAction::class)->execute(createStudentData($f));

    $updated = app(UpdateStudentProfileAction::class)->execute(new UpdateStudentProfileData(
        studentId: $student->id, updatedByUserId: $f['user']->id, city: 'Harare',
    ));

    expect($updated->city)->toBe('Harare')
        ->and($updated->enrolment_type)->toBe($student->enrolment_type);
});

it('suspends a student without affecting billing (BR-PPL-01-012/AC-PPL-01-006)', function (): void {
    $f = studentFixture();
    $student = app(CreateStudentAction::class)->execute(createStudentData($f));
    app(ChangeStudentStatusAction::class)->execute(new ChangeStudentStatusData($student->id, 'active', $f['user']->id));

    $suspended = app(ChangeStudentStatusAction::class)->execute(new ChangeStudentStatusData(
        studentId: $student->id, newStatus: 'suspended', changedByUserId: $f['user']->id, reasonCode: 'disciplinary',
    ));

    expect($suspended->status)->toBe('suspended')
        // Nothing in this engine reduces or waives a suspended
        // learner's charge — that would require a separate, approved
        // FIN-03 waiver, which is exactly BR-PPL-01-012's point.
        ->and($suspended->enrolment_type)->toBe($student->enrolment_type)
        ->and($suspended->residency)->toBe($student->residency);
});

it('is append-only: student_attribute_changes refuses a disallowed update and any delete', function (): void {
    $f = studentFixture();
    $student = app(CreateStudentAction::class)->execute(createStudentData($f));

    $change = app(ChangeBillingAttributeAction::class)->execute(new ChangeBillingAttributeData(
        studentId: $student->id, attribute: 'residency', newValue: 'DAY', effectiveFrom: now(), changedByUserId: $f['user']->id,
    ));

    // Each attempt reloads a fresh instance — Eloquent's dirty-tracking
    // on the in-memory object persists across a failed save, so reusing
    // the same instance would make the next assertion fail for the
    // wrong reason (still-dirty `new_value` from the attempt before it).
    expect(fn () => $change->fresh()->update(['new_value' => 'BOARDER']))->toThrow(InvalidStateTransitionException::class);
    expect(fn () => $change->fresh()->delete())->toThrow(InvalidStateTransitionException::class);

    // The one permitted column.
    $change->fresh()->update(['rebilling_status' => 'applied']);
    expect($change->fresh()->rebilling_status)->toBe('applied');
});
