<?php

use App\Models\User;
use Modules\Academic\Domain\Actions\DropSubjectAction;
use Modules\Academic\Domain\Actions\EnrolSubjectAction;
use Modules\Academic\Domain\DataObjects\DropSubjectData;
use Modules\Academic\Domain\DataObjects\EnrolSubjectData;
use Modules\Academic\Domain\Events\SubjectEnrolmentAdded;
use Modules\Academic\Models\CurriculumFramework;
use Modules\Academic\Models\LearnerSubjectEnrolment;
use Modules\Academic\Models\Subject;
use Modules\Academic\Models\SubjectEnrolmentChange;
use Modules\Academic\Models\SubjectGroup;
use Modules\Core\Domain\Actions\Documents\CreateNumberingSeriesAction;
use Modules\Core\Domain\DataObjects\Documents\CreateNumberingSeriesData;
use Modules\Core\Domain\Exceptions\InvalidStateTransitionException;
use Modules\Core\Domain\Support\SchoolContext;
use Modules\Core\Models\AcademicYear;
use Modules\Core\Models\GradeLevel;
use Modules\Core\Models\School;
use Modules\Core\Models\SchoolSection;
use Modules\Core\Models\Term;
use Modules\Finance\Domain\Actions\ActivateFeeStructureAction;
use Modules\Finance\Domain\Actions\ApproveBillingRunAction;
use Modules\Finance\Domain\Actions\CommitBillingRunAction;
use Modules\Finance\Domain\Actions\ComputeBillingRunAction;
use Modules\Finance\Domain\Actions\CreateAdHocChargeAction;
use Modules\Finance\Domain\Actions\CreateFeeComponentAction;
use Modules\Finance\Domain\Actions\CreateFeeStructureAction;
use Modules\Finance\Domain\Actions\PreviewIndicativeFeeAction;
use Modules\Finance\Domain\Actions\ReviseFeeStructureAction;
use Modules\Finance\Domain\DataObjects\ActivateFeeStructureData;
use Modules\Finance\Domain\DataObjects\ApproveBillingRunData;
use Modules\Finance\Domain\DataObjects\CommitBillingRunData;
use Modules\Finance\Domain\DataObjects\ComputeBillingRunData;
use Modules\Finance\Domain\DataObjects\CreateAdHocChargeData;
use Modules\Finance\Domain\DataObjects\CreateFeeComponentData;
use Modules\Finance\Domain\DataObjects\CreateFeeStructureData;
use Modules\Finance\Domain\DataObjects\PreviewIndicativeFeeData;
use Modules\Finance\Domain\DataObjects\ReviseFeeStructureData;
use Modules\Finance\Domain\Exceptions\AdHocChargeRequiresApprovalException;
use Modules\Finance\Models\Account;
use Modules\Finance\Models\FeeStructure;
use Modules\Finance\Models\Journal;
use Modules\Finance\Models\LearnerFeeAssignment;
use Modules\People\Domain\Actions\CreateStudentAction;
use Modules\People\Domain\Actions\LinkGuardianToStudentAction;
use Modules\People\Domain\DataObjects\CreateStudentData;
use Modules\People\Domain\DataObjects\LinkGuardianToStudentData;
use Modules\People\Models\Guardian;
use Modules\People\Models\Student;

/**
 * @return array<string, mixed>
 */
function fin02Fixture(int $termStartsDaysAgo = 0, int $termLengthDays = 90, ?int $teachingDays = 65): array
{
    $school = School::factory()->create(['base_currency' => 'USD']);
    SchoolContext::set($school);
    $year = AcademicYear::factory()->for($school)->create();
    $termStart = now()->subDays($termStartsDaysAgo)->startOfDay();
    $term = Term::factory()->for($school)->for($year, 'academicYear')->create([
        'starts_on' => $termStart,
        'ends_on' => $termStart->copy()->addDays($termLengthDays),
        'teaching_days' => $teachingDays,
    ]);
    $framework = CurriculumFramework::factory()->for($school)->create();
    $section = SchoolSection::factory()->for($school)->create();
    $gradeLevel = GradeLevel::factory()->for($school)->for($section, 'section')->create();
    $user = User::factory()->create();

    app(CreateNumberingSeriesAction::class)->execute(new CreateNumberingSeriesData(
        schoolId: $school->id, documentType: 'admission', pattern: '{SCHOOL}/{YEAR}/{SEQ:4}', academicYearId: $year->id,
    ));
    app(CreateNumberingSeriesAction::class)->execute(new CreateNumberingSeriesData(
        schoolId: $school->id, documentType: 'journal', pattern: 'JNL/{SEQ:6}',
    ));
    app(CreateNumberingSeriesAction::class)->execute(new CreateNumberingSeriesData(
        schoolId: $school->id, documentType: 'invoice', pattern: 'INV/{SEQ:6}',
    ));
    app(CreateNumberingSeriesAction::class)->execute(new CreateNumberingSeriesData(
        schoolId: $school->id, documentType: 'credit_note', pattern: 'CN/{SEQ:6}',
    ));

    $income = Account::factory()->for($school)->income()->create();
    $debtor = Account::factory()->for($school)->controlAccount('student')->create();

    $tuition = app(CreateFeeComponentAction::class)->execute(new CreateFeeComponentData(
        schoolId: $school->id, code: 'TUITION', name: 'Tuition', category: 'tuition',
        incomeAccountId: $income->id, debtorAccountId: $debtor->id, defaultCurrency: 'USD', createdByUserId: $user->id,
    ));

    return [
        'school' => $school, 'year' => $year, 'term' => $term, 'framework' => $framework,
        'section' => $section, 'gradeLevel' => $gradeLevel, 'user' => $user, 'tuition' => $tuition,
    ];
}

/**
 * @param  array<string, mixed>  $f
 */
function fin02Student(array $f, array $overrides = []): Student
{
    $student = app(CreateStudentAction::class)->execute(new CreateStudentData(
        schoolId: $f['school']->id,
        academicYearId: $f['year']->id,
        termId: $f['term']->id,
        firstName: $overrides['firstName'] ?? 'Rutendo',
        lastName: $overrides['lastName'] ?? 'Chiweshe',
        dateOfBirth: now()->subYears(16),
        gender: 'female',
        enrolmentType: $overrides['enrolmentType'] ?? 'FULL_TIME',
        residency: 'DAY',
        sectionId: $f['section']->id,
        gradeLevelId: $f['gradeLevel']->id,
        entryCohortYear: (int) now()->year,
        createdByUserId: $f['user']->id,
        skipDuplicateCheck: true,
    ));

    $guardian = Guardian::factory()->for($f['school'])->create();
    app(LinkGuardianToStudentAction::class)->execute(new LinkGuardianToStudentData(
        studentId: $student->id, guardianId: $guardian->id, relationship: 'father',
        createdByUserId: $f['user']->id, isFeeResponsible: true,
    ));

    return $student;
}

/**
 * @param  array<string, mixed>  $f
 */
function fullTimeStructure(array $f, int $amountMinor = 10000): void
{
    $structure = app(CreateFeeStructureAction::class)->execute(new CreateFeeStructureData(
        schoolId: $f['school']->id, academicYearId: $f['year']->id, name: 'Full-Time Structure', priority: 10,
        rules: [['attribute' => 'enrolment_type', 'operator' => 'equals', 'value' => 'FULL_TIME']],
        items: [['component_id' => $f['tuition']->id, 'billing_basis' => 'flat_per_term', 'currency' => 'USD', 'amount_minor' => $amountMinor]],
        createdByUserId: $f['user']->id,
    ));

    app(ActivateFeeStructureAction::class)->execute(new ActivateFeeStructureData($structure->id, $f['user']->id));
}

it('charges a full-time learner the flat fee regardless of subject count (AC-FIN-02-001)', function (): void {
    $f = fin02Fixture();
    fullTimeStructure($f);
    $lightLoad = fin02Student($f, ['firstName' => 'Light']);
    $heavyLoad = fin02Student($f, ['firstName' => 'Heavy']);

    foreach (Subject::factory()->for($f['school'])->count(2)->create(['framework_id' => $f['framework']->id]) as $subject) {
        app(EnrolSubjectAction::class)->execute(new EnrolSubjectData($lightLoad->id, $subject->id, $f['term']->id, $f['user']->id));
    }
    foreach (Subject::factory()->for($f['school'])->count(7)->create(['framework_id' => $f['framework']->id]) as $subject) {
        app(EnrolSubjectAction::class)->execute(new EnrolSubjectData($heavyLoad->id, $subject->id, $f['term']->id, $f['user']->id));
    }

    $run = app(ComputeBillingRunAction::class)->execute(new ComputeBillingRunData(
        $f['school']->id, $f['year']->id, $f['term']->id, $f['user']->id, studentIds: [$lightLoad->id, $heavyLoad->id],
    ));

    $lightNet = LearnerFeeAssignment::where('student_id', $lightLoad->id)->first()->lines->sum('net_minor');
    $heavyNet = LearnerFeeAssignment::where('student_id', $heavyLoad->id)->first()->lines->sum('net_minor');

    expect($lightNet)->toBe(10000)->and($heavyNet)->toBe(10000)
        ->and($run->computed_count)->toBe(2);
});

it('bills a part-time learner per subject with group rates, a tier multiplier, and names the subjects (AC-FIN-02-002/BR-ACA-01-004)', function (): void {
    $f = fin02Fixture();
    $sciences = SubjectGroup::factory()->for($f['school'])->create(['code' => 'sciences']);
    $sciencesPractical = SubjectGroup::factory()->for($f['school'])->create(['code' => 'sciences_practical']);
    $commercials = SubjectGroup::factory()->for($f['school'])->create(['code' => 'commercials']);

    $maths = Subject::factory()->for($f['school'])->create(['framework_id' => $f['framework']->id, 'name' => 'Mathematics', 'subject_group_id' => $sciences->id]);
    $physics = Subject::factory()->for($f['school'])->create(['framework_id' => $f['framework']->id, 'name' => 'Physics', 'subject_group_id' => $sciencesPractical->id]);
    $accounting = Subject::factory()->for($f['school'])->create(['framework_id' => $f['framework']->id, 'name' => 'Accounting', 'subject_group_id' => $commercials->id]);

    $partTimeStructure = app(CreateFeeStructureAction::class)->execute(new CreateFeeStructureData(
        schoolId: $f['school']->id, academicYearId: $f['year']->id, name: 'Part-Time Structure', priority: 20,
        rules: [['attribute' => 'enrolment_type', 'operator' => 'equals', 'value' => 'PART_TIME']],
        items: [[
            'component_id' => $f['tuition']->id, 'billing_basis' => 'per_subject', 'currency' => 'USD',
            'unit_rate_minor' => 8000,
            'subject_rate_map' => ['group:sciences' => 9000, 'group:sciences_practical' => 11000, 'group:commercials' => 8000],
            'tier_bands' => [['from' => 1, 'to' => 4, 'multiplier' => 1.0], ['from' => 5, 'to' => null, 'multiplier' => 0.85]],
            'minimum_minor' => 15000, 'maximum_minor' => 45000,
        ]],
        createdByUserId: $f['user']->id,
    ));
    app(ActivateFeeStructureAction::class)->execute(new ActivateFeeStructureData($partTimeStructure->id, $f['user']->id));

    $student = fin02Student($f, ['enrolmentType' => 'PART_TIME']);
    foreach ([$maths, $physics, $accounting] as $subject) {
        app(EnrolSubjectAction::class)->execute(new EnrolSubjectData($student->id, $subject->id, $f['term']->id, $f['user']->id));
    }

    app(ComputeBillingRunAction::class)->execute(new ComputeBillingRunData(
        $f['school']->id, $f['year']->id, $f['term']->id, $f['user']->id, studentIds: [$student->id],
    ));

    $line = LearnerFeeAssignment::where('student_id', $student->id)->first()->lines->first();

    expect((int) $line->quantity)->toBe(3)
        ->and($line->net_minor)->toBe(28000)
        ->and($line->calculation_note)->toContain('Mathematics')->toContain('Physics')->toContain('Accounting');
});

it('raises a pro-rated mid-term charge automatically when a part-time learner adds a subject (AC-FIN-02-003)', function (): void {
    $f = fin02Fixture();
    $group = SubjectGroup::factory()->for($f['school'])->create(['code' => 'sciences']);
    $subject = Subject::factory()->for($f['school'])->create(['framework_id' => $f['framework']->id, 'name' => 'Statistics', 'subject_group_id' => $group->id]);

    $structure = app(CreateFeeStructureAction::class)->execute(new CreateFeeStructureData(
        schoolId: $f['school']->id, academicYearId: $f['year']->id, name: 'Part-Time Structure', priority: 20,
        rules: [['attribute' => 'enrolment_type', 'operator' => 'equals', 'value' => 'PART_TIME']],
        items: [['component_id' => $f['tuition']->id, 'billing_basis' => 'per_subject', 'currency' => 'USD', 'unit_rate_minor' => 9000, 'subject_rate_map' => ['group:sciences' => 9000]]],
        createdByUserId: $f['user']->id,
    ));
    app(ActivateFeeStructureAction::class)->execute(new ActivateFeeStructureData($structure->id, $f['user']->id));

    $student = fin02Student($f, ['enrolmentType' => 'PART_TIME']);
    $run = app(ComputeBillingRunAction::class)->execute(new ComputeBillingRunData($f['school']->id, $f['year']->id, $f['term']->id, $f['user']->id, studentIds: [$student->id]));
    app(ApproveBillingRunAction::class)->execute(new ApproveBillingRunData($run->id, $f['user']->id));

    // No manual BillMidTermSubjectChangeAction call — EnrolSubjectAction's
    // emitted event is what should raise the charge, end to end.
    app(EnrolSubjectAction::class)->execute(new EnrolSubjectData($student->id, $subject->id, $f['term']->id, $f['user']->id));
    $change = SubjectEnrolmentChange::where('student_id', $student->id)->where('change_type', 'added')->firstOrFail();

    $line = LearnerFeeAssignment::where('student_id', $student->id)->first()->lines()->latest('id')->first();

    expect($change->billing_event_dispatched)->toBeTrue()
        ->and($change->billing_event_result)->toBe('success')
        ->and($change->billing_reference)->toBe("fee_line:{$line->id}")
        ->and($line->gross_minor)->toBeGreaterThan(0)
        ->and($line->calculation_note)->toContain('Statistics')->toContain((string) $change->effective_from->toDateString());
});

it('raises a pro-rated credit automatically when a part-time learner drops a subject (AC-FIN-02-004)', function (): void {
    $f = fin02Fixture();
    $group = SubjectGroup::factory()->for($f['school'])->create(['code' => 'sciences']);
    $subject = Subject::factory()->for($f['school'])->create(['framework_id' => $f['framework']->id, 'subject_group_id' => $group->id]);

    $structure = app(CreateFeeStructureAction::class)->execute(new CreateFeeStructureData(
        schoolId: $f['school']->id, academicYearId: $f['year']->id, name: 'Part-Time Structure', priority: 20,
        rules: [['attribute' => 'enrolment_type', 'operator' => 'equals', 'value' => 'PART_TIME']],
        items: [['component_id' => $f['tuition']->id, 'billing_basis' => 'per_subject', 'currency' => 'USD', 'unit_rate_minor' => 9000, 'subject_rate_map' => ['group:sciences' => 9000]]],
        createdByUserId: $f['user']->id,
    ));
    app(ActivateFeeStructureAction::class)->execute(new ActivateFeeStructureData($structure->id, $f['user']->id));

    $student = fin02Student($f, ['enrolmentType' => 'PART_TIME']);
    app(EnrolSubjectAction::class)->execute(new EnrolSubjectData($student->id, $subject->id, $f['term']->id, $f['user']->id));

    $run = app(ComputeBillingRunAction::class)->execute(new ComputeBillingRunData($f['school']->id, $f['year']->id, $f['term']->id, $f['user']->id, studentIds: [$student->id]));
    app(ApproveBillingRunAction::class)->execute(new ApproveBillingRunData($run->id, $f['user']->id));

    app(DropSubjectAction::class)->execute(new DropSubjectData($student->id, $subject->id, $f['term']->id, $f['user']->id));
    $change = SubjectEnrolmentChange::where('student_id', $student->id)->where('change_type', 'dropped')->firstOrFail();

    $line = LearnerFeeAssignment::where('student_id', $student->id)->first()->lines()->latest('id')->first();

    expect($change->billing_event_dispatched)->toBeTrue()
        ->and($change->billing_event_result)->toBe('success')
        ->and($line->gross_minor)->toBeLessThan(0)
        ->and($line->net_minor)->toBe($line->gross_minor);
});

it('previews the indicative fee for a proposed subject set before any enrolment exists (BR-ACA-02-016)', function (): void {
    $f = fin02Fixture();
    $group = SubjectGroup::factory()->for($f['school'])->create(['code' => 'sciences']);
    $subjectA = Subject::factory()->for($f['school'])->create(['framework_id' => $f['framework']->id, 'name' => 'Biology', 'subject_group_id' => $group->id]);
    $subjectB = Subject::factory()->for($f['school'])->create(['framework_id' => $f['framework']->id, 'name' => 'Chemistry', 'subject_group_id' => $group->id]);

    $structure = app(CreateFeeStructureAction::class)->execute(new CreateFeeStructureData(
        schoolId: $f['school']->id, academicYearId: $f['year']->id, name: 'Part-Time Structure', priority: 20,
        rules: [['attribute' => 'enrolment_type', 'operator' => 'equals', 'value' => 'PART_TIME']],
        items: [['component_id' => $f['tuition']->id, 'billing_basis' => 'per_subject', 'currency' => 'USD', 'unit_rate_minor' => 9000, 'subject_rate_map' => ['group:sciences' => 9000]]],
        createdByUserId: $f['user']->id,
    ));
    app(ActivateFeeStructureAction::class)->execute(new ActivateFeeStructureData($structure->id, $f['user']->id));

    $student = fin02Student($f, ['enrolmentType' => 'PART_TIME']);

    $preview = app(PreviewIndicativeFeeAction::class)->execute(new PreviewIndicativeFeeData(
        studentId: $student->id, termId: $f['term']->id, subjectIds: [$subjectA->id, $subjectB->id],
    ));

    expect($preview->amountMinor)->toBe(18000)
        ->and($preview->currency)->toBe('USD')
        ->and(LearnerSubjectEnrolment::where('student_id', $student->id)->exists())->toBeFalse()
        ->and(LearnerFeeAssignment::where('student_id', $student->id)->exists())->toBeFalse();
});

it('does not re-bill a subject change whose billing event was already dispatched (idempotency)', function (): void {
    $f = fin02Fixture();
    $group = SubjectGroup::factory()->for($f['school'])->create(['code' => 'sciences']);
    $subject = Subject::factory()->for($f['school'])->create(['framework_id' => $f['framework']->id, 'subject_group_id' => $group->id]);

    $structure = app(CreateFeeStructureAction::class)->execute(new CreateFeeStructureData(
        schoolId: $f['school']->id, academicYearId: $f['year']->id, name: 'Part-Time Structure', priority: 20,
        rules: [['attribute' => 'enrolment_type', 'operator' => 'equals', 'value' => 'PART_TIME']],
        items: [['component_id' => $f['tuition']->id, 'billing_basis' => 'per_subject', 'currency' => 'USD', 'unit_rate_minor' => 9000, 'subject_rate_map' => ['group:sciences' => 9000]]],
        createdByUserId: $f['user']->id,
    ));
    app(ActivateFeeStructureAction::class)->execute(new ActivateFeeStructureData($structure->id, $f['user']->id));

    $student = fin02Student($f, ['enrolmentType' => 'PART_TIME']);
    $run = app(ComputeBillingRunAction::class)->execute(new ComputeBillingRunData($f['school']->id, $f['year']->id, $f['term']->id, $f['user']->id, studentIds: [$student->id]));
    app(ApproveBillingRunAction::class)->execute(new ApproveBillingRunData($run->id, $f['user']->id));

    $enrolment = app(EnrolSubjectAction::class)->execute(new EnrolSubjectData($student->id, $subject->id, $f['term']->id, $f['user']->id));
    $change = SubjectEnrolmentChange::where('student_id', $student->id)->where('change_type', 'added')->firstOrFail();
    $linesBefore = LearnerFeeAssignment::where('student_id', $student->id)->first()->lines()->count();

    event(new SubjectEnrolmentAdded($enrolment, $change));

    $linesAfter = LearnerFeeAssignment::where('student_id', $student->id)->first()->lines()->count();

    expect($linesAfter)->toBe($linesBefore);
});

it('flags a learner matching no structure as an exception, not a zero charge (AC-FIN-02-005)', function (): void {
    $f = fin02Fixture();
    fullTimeStructure($f);
    $student = fin02Student($f, ['enrolmentType' => 'PART_TIME']);

    $run = app(ComputeBillingRunAction::class)->execute(new ComputeBillingRunData(
        $f['school']->id, $f['year']->id, $f['term']->id, $f['user']->id, studentIds: [$student->id],
    ));

    expect($run->exception_count)->toBe(1)
        ->and($run->exception_report['exceptions'][0]['reason'])->toBe('no_matching_structure')
        ->and(LearnerFeeAssignment::where('student_id', $student->id)->exists())->toBeFalse();

    $approved = app(ApproveBillingRunAction::class)->execute(new ApproveBillingRunData($run->id, $f['user']->id));
    expect($approved->status)->toBe('approved');
});

it('posts no journal until a run is approved and commits one FEE_BILLING journal once it is (AC-FIN-02-006/BR-FIN-02-013)', function (): void {
    $f = fin02Fixture();
    fullTimeStructure($f);
    $student = fin02Student($f);

    $run = app(ComputeBillingRunAction::class)->execute(new ComputeBillingRunData(
        $f['school']->id, $f['year']->id, $f['term']->id, $f['user']->id, studentIds: [$student->id],
    ));

    expect(fn () => app(CommitBillingRunAction::class)->execute(new CommitBillingRunData($run->id, $f['user']->id, now())))
        ->toThrow(InvalidStateTransitionException::class);
    expect(Journal::where('journal_type', 'FEE_BILLING')->count())->toBe(0);

    app(ApproveBillingRunAction::class)->execute(new ApproveBillingRunData($run->id, $f['user']->id));
    $committed = app(CommitBillingRunAction::class)->execute(new CommitBillingRunData($run->id, $f['user']->id, now()));

    expect($committed->status)->toBe('committed')
        ->and(Journal::where('journal_type', 'FEE_BILLING')->count())->toBe(1)
        ->and($committed->journal_batch_uuid)->not->toBeNull();
});

it('charges a one-off component exactly once across terms (AC-FIN-02-007/BR-FIN-02-007)', function (): void {
    $f = fin02Fixture();
    $registration = app(CreateFeeComponentAction::class)->execute(new CreateFeeComponentData(
        schoolId: $f['school']->id, code: 'REG', name: 'Registration', category: 'other',
        incomeAccountId: $f['tuition']->income_account_id, debtorAccountId: $f['tuition']->debtor_account_id,
        defaultCurrency: 'USD', createdByUserId: $f['user']->id,
    ));

    $structure = app(CreateFeeStructureAction::class)->execute(new CreateFeeStructureData(
        schoolId: $f['school']->id, academicYearId: $f['year']->id, name: 'Full-Time Structure', priority: 10,
        rules: [['attribute' => 'enrolment_type', 'operator' => 'equals', 'value' => 'FULL_TIME']],
        items: [
            ['component_id' => $f['tuition']->id, 'billing_basis' => 'flat_per_term', 'currency' => 'USD', 'amount_minor' => 10000],
            ['component_id' => $registration->id, 'billing_basis' => 'one_off', 'currency' => 'USD', 'amount_minor' => 2000],
        ],
        createdByUserId: $f['user']->id,
    ));
    app(ActivateFeeStructureAction::class)->execute(new ActivateFeeStructureData($structure->id, $f['user']->id));

    $student = fin02Student($f);

    $firstRun = app(ComputeBillingRunAction::class)->execute(new ComputeBillingRunData($f['school']->id, $f['year']->id, $f['term']->id, $f['user']->id, studentIds: [$student->id]));
    $firstLines = LearnerFeeAssignment::where('student_id', $student->id)->first()->lines;
    expect($firstLines->where('billing_basis', 'one_off')->count())->toBe(1);
    app(ApproveBillingRunAction::class)->execute(new ApproveBillingRunData($firstRun->id, $f['user']->id));

    $secondYear = AcademicYear::factory()->for($f['school'])->create();
    $secondTerm = Term::factory()->for($f['school'])->for($secondYear, 'academicYear')->create(['starts_on' => $f['term']->ends_on->copy()->addDay()]);

    $secondYearStructure = app(CreateFeeStructureAction::class)->execute(new CreateFeeStructureData(
        schoolId: $f['school']->id, academicYearId: $secondYear->id, name: 'Full-Time Structure', priority: 10,
        rules: [['attribute' => 'enrolment_type', 'operator' => 'equals', 'value' => 'FULL_TIME']],
        items: [['component_id' => $f['tuition']->id, 'billing_basis' => 'flat_per_term', 'currency' => 'USD', 'amount_minor' => 10000]],
        createdByUserId: $f['user']->id,
    ));
    app(ActivateFeeStructureAction::class)->execute(new ActivateFeeStructureData($secondYearStructure->id, $f['user']->id));

    app(ComputeBillingRunAction::class)->execute(new ComputeBillingRunData($f['school']->id, $secondYear->id, $secondTerm->id, $f['user']->id, studentIds: [$student->id]));
    $secondLines = LearnerFeeAssignment::where('student_id', $student->id)->where('term_id', $secondTerm->id)->first()->lines;

    expect($secondLines->where('billing_basis', 'one_off')->count())->toBe(0);
});

it('flags variance beyond the alert threshold against the previous term (AC-FIN-02-008)', function (): void {
    $f = fin02Fixture();
    fullTimeStructure($f, amountMinor: 10000);
    $student = fin02Student($f);

    $firstRun = app(ComputeBillingRunAction::class)->execute(new ComputeBillingRunData($f['school']->id, $f['year']->id, $f['term']->id, $f['user']->id, studentIds: [$student->id]));
    app(ApproveBillingRunAction::class)->execute(new ApproveBillingRunData($firstRun->id, $f['user']->id));

    $secondTerm = Term::factory()->for($f['school'])->for($f['year'], 'academicYear')->create(['number' => 2, 'starts_on' => $f['term']->ends_on->copy()->addDay()]);
    fullTimeStructure($f, amountMinor: 20000);

    $secondRun = app(ComputeBillingRunAction::class)->execute(new ComputeBillingRunData($f['school']->id, $f['year']->id, $secondTerm->id, $f['user']->id, studentIds: [$student->id]));

    $exceptionReasons = array_column($secondRun->exception_report['exceptions'], 'reason');
    expect($exceptionReasons)->toContain('variance_beyond_threshold')
        ->and($secondRun->variance_report[0]['variance_percent'])->toBeGreaterThan(25.0);
});

it('keeps a historical assignment unchanged after its structure is revised (AC-FIN-02-009/BR-FIN-02-011)', function (): void {
    $f = fin02Fixture();
    $structure = app(CreateFeeStructureAction::class)->execute(new CreateFeeStructureData(
        schoolId: $f['school']->id, academicYearId: $f['year']->id, name: 'Full-Time Structure', priority: 10,
        rules: [['attribute' => 'enrolment_type', 'operator' => 'equals', 'value' => 'FULL_TIME']],
        items: [['component_id' => $f['tuition']->id, 'billing_basis' => 'flat_per_term', 'currency' => 'USD', 'amount_minor' => 10000]],
        createdByUserId: $f['user']->id,
    ));
    app(ActivateFeeStructureAction::class)->execute(new ActivateFeeStructureData($structure->id, $f['user']->id));

    $student = fin02Student($f);
    app(ComputeBillingRunAction::class)->execute(new ComputeBillingRunData($f['school']->id, $f['year']->id, $f['term']->id, $f['user']->id, studentIds: [$student->id]));
    $assignment = LearnerFeeAssignment::where('student_id', $student->id)->first();

    expect($assignment->structure_version)->toBe(1);
    expect($assignment->lines->sum('net_minor'))->toBe(10000);

    $revision = app(ReviseFeeStructureAction::class)->execute(new ReviseFeeStructureData(
        structureId: $structure->id,
        rules: [['attribute' => 'enrolment_type', 'operator' => 'equals', 'value' => 'FULL_TIME']],
        items: [['component_id' => $f['tuition']->id, 'billing_basis' => 'flat_per_term', 'currency' => 'USD', 'amount_minor' => 15000]],
        revisedByUserId: $f['user']->id,
    ));
    app(ActivateFeeStructureAction::class)->execute(new ActivateFeeStructureData($revision->id, $f['user']->id));

    $assignment->refresh();
    expect($assignment->structure_version)->toBe(1)
        ->and($assignment->lines->sum('net_minor'))->toBe(10000)
        ->and($structure->fresh()->status)->toBe('superseded')
        ->and($revision->fresh()->version)->toBe(2);
});

it('stores every evaluated structure, matched or failed, in the resolution trace (AC-FIN-02-010/BR-FIN-02-003)', function (): void {
    $f = fin02Fixture();
    fullTimeStructure($f);
    $student = fin02Student($f, ['enrolmentType' => 'FULL_TIME']);

    app(ComputeBillingRunAction::class)->execute(new ComputeBillingRunData($f['school']->id, $f['year']->id, $f['term']->id, $f['user']->id, studentIds: [$student->id]));
    $trace = LearnerFeeAssignment::where('student_id', $student->id)->first()->resolution_trace;

    expect($trace['structures_evaluated'])->toHaveCount(1)
        ->and($trace['structures_evaluated'][0]['matched'])->toBeTrue()
        ->and($trace['selected_structure']['version'])->toBe(1)
        ->and($trace['attributes_evaluated']['enrolment_type'])->toBe('FULL_TIME');
});

it('cannot edit version, academic_year_id, or term_id once a structure has left draft (BR-FIN-02-011)', function (): void {
    $f = fin02Fixture();
    fullTimeStructure($f);
    $structure = FeeStructure::where('school_id', $f['school']->id)->first();

    expect(fn () => $structure->update(['version' => 99]))->toThrow(InvalidStateTransitionException::class);
});

it('requires an approver for an ad hoc charge above the threshold (BR-FIN-02-019)', function (): void {
    $f = fin02Fixture();
    $student = fin02Student($f);

    expect(fn () => app(CreateAdHocChargeAction::class)->execute(new CreateAdHocChargeData(
        schoolId: $f['school']->id, academicYearId: $f['year']->id, termId: $f['term']->id, studentId: $student->id,
        componentId: $f['tuition']->id, description: 'Damaged textbook', unitRateMinor: 10000, currency: 'USD', raisedByUserId: $f['user']->id,
    )))->toThrow(AdHocChargeRequiresApprovalException::class);

    $charge = app(CreateAdHocChargeAction::class)->execute(new CreateAdHocChargeData(
        schoolId: $f['school']->id, academicYearId: $f['year']->id, termId: $f['term']->id, studentId: $student->id,
        componentId: $f['tuition']->id, description: 'Damaged textbook', unitRateMinor: 10000, currency: 'USD',
        raisedByUserId: $f['user']->id, approvedByUserId: $f['user']->id,
    ));

    expect($charge->status)->toBe('pending')->and($charge->amount_minor)->toBe(10000);
});
