<?php

use App\Models\User;
use Modules\Academic\Models\CurriculumFramework;
use Modules\Academic\Models\ExaminationCandidate;
use Modules\Academic\Models\ExaminationSession;
use Modules\Academic\Models\Subject;
use Modules\Academic\Models\SubjectSelectionRule;
use Modules\Compliance\Domain\Actions\AnalyseZimsecPassRatesAction;
use Modules\Compliance\Domain\Actions\BillZimsecEntryFeesAction;
use Modules\Compliance\Domain\Actions\CheckZimsecDeadlinesAction;
use Modules\Compliance\Domain\Actions\CloseZimsecRegistrationAction;
use Modules\Compliance\Domain\Actions\ConfirmStatementOfEntryAction;
use Modules\Compliance\Domain\Actions\CreateZimsecRegistrationAction;
use Modules\Compliance\Domain\Actions\CreateZimsecValidationRuleAction;
use Modules\Compliance\Domain\Actions\DeriveZimsecCandidatesAction;
use Modules\Compliance\Domain\Actions\DistributeStatementsOfEntryAction;
use Modules\Compliance\Domain\Actions\ExportZimsecRegistrationAction;
use Modules\Compliance\Domain\Actions\ImportZimsecResultsAction;
use Modules\Compliance\Domain\Actions\RecordZimsecFeeCollectionAction;
use Modules\Compliance\Domain\Actions\ValidateZimsecCandidatesAction;
use Modules\Compliance\Domain\DataObjects\BillZimsecEntryFeesData;
use Modules\Compliance\Domain\DataObjects\CreateZimsecRegistrationData;
use Modules\Compliance\Domain\DataObjects\CreateZimsecValidationRuleData;
use Modules\Compliance\Domain\DataObjects\ExportZimsecRegistrationData;
use Modules\Compliance\Domain\DataObjects\ImportZimsecResultsData;
use Modules\Compliance\Domain\DataObjects\RecordZimsecFeeCollectionData;
use Modules\Compliance\Domain\Exceptions\ZimsecExportBlockedException;
use Modules\Compliance\Domain\Exceptions\ZimsecFeeShortfallException;
use Modules\Compliance\Models\ZimsecCandidate;
use Modules\Compliance\Models\ZimsecResult;
use Modules\Core\Domain\Support\SchoolContext;
use Modules\Core\Models\AcademicYear;
use Modules\Core\Models\School;
use Modules\Core\Models\Term;
use Modules\Finance\Domain\Actions\CreateFeeComponentAction;
use Modules\Finance\Domain\DataObjects\CreateFeeComponentData;
use Modules\Finance\Models\Account;
use Modules\People\Models\Student;
use Modules\People\Models\StudentGuardian;
use Modules\People\Models\StudentPriorResult;

/**
 * @return array<string, mixed>
 */
function cmp01Fixture(): array
{
    $school = School::factory()->create(['base_currency' => 'USD']);
    SchoolContext::set($school);
    $year = AcademicYear::factory()->for($school)->create();
    $term = Term::factory()->for($school)->for($year, 'academicYear')->create();
    $user = User::factory()->create();
    $framework = CurriculumFramework::factory()->for($school)->create();

    $english = Subject::factory()->for($school)->for($framework, 'framework')->create(['code' => 'ENG', 'name' => 'English Language', 'zimsec_subject_code' => '4021']);
    $maths = Subject::factory()->for($school)->for($framework, 'framework')->create(['code' => 'MATH', 'name' => 'Mathematics', 'zimsec_subject_code' => '4028']);

    $session = ExaminationSession::factory()->for($school)->for($year, 'academicYear')->for($term)->create([
        'exam_body' => 'ZIMSEC',
        'status' => 'in_progress',
    ]);

    $income = Account::factory()->for($school)->income()->create();
    $debtor = Account::factory()->for($school)->controlAccount('student')->create();
    $feeComponent = app(CreateFeeComponentAction::class)->execute(new CreateFeeComponentData(
        schoolId: $school->id, code: 'ZIMSEC', name: 'ZIMSEC Entry Fee', category: 'other',
        incomeAccountId: $income->id, debtorAccountId: $debtor->id, defaultCurrency: 'USD',
        createdByUserId: $user->id, isFiscalisable: false, taxCategory: 'exempt',
    ));

    $registration = app(CreateZimsecRegistrationAction::class)->execute(new CreateZimsecRegistrationData(
        schoolId: $school->id, academicYearId: $year->id, examLevel: 'o_level', examSeries: 'November 2026',
        centreNumber: '12345', registrationClosesOn: now()->addMonth(), currency: 'USD',
        examinationSessionId: $session->id,
    ));

    return compact('school', 'year', 'term', 'user', 'framework', 'english', 'maths', 'session', 'feeComponent', 'registration');
}

/**
 * @param  array<int, int>  $subjectIds
 */
function cmp01ConfirmCandidate(array $f, ?Student $student = null, array $subjectIds = [], int $entryFeeMinor = 2000): ExaminationCandidate
{
    $student ??= Student::factory()->for($f['school'])->create();

    if ($subjectIds === []) {
        $subjectIds = [$f['english']->id, $f['maths']->id];
    }

    return ExaminationCandidate::factory()->create([
        'school_id' => $f['school']->id,
        'session_id' => $f['session']->id,
        'student_id' => $student->id,
        'entry_status' => 'confirmed',
        'entered_subjects' => $subjectIds,
        'entry_fee_minor' => $entryFeeMinor,
        'entry_fee_currency' => 'USD',
    ]);
}

it('derives candidates from confirmed ACA-07 entries with bio-data and subjects copied, never re-keyed (AC-CMP-01-003)', function (): void {
    $f = cmp01Fixture();
    $student = Student::factory()->for($f['school'])->create(['first_name' => 'Tendai', 'last_name' => 'Moyo', 'gender' => 'male']);
    cmp01ConfirmCandidate($f, $student);

    $candidates = app(DeriveZimsecCandidatesAction::class)->execute($f['registration']->id);

    expect($candidates)->toHaveCount(1);
    $candidate = $candidates->first();
    expect($candidate->surname)->toBe('Moyo')
        ->and($candidate->forenames)->toContain('Tendai')
        ->and($candidate->gender)->toBe('male')
        ->and($candidate->subject_count)->toBe(2)
        ->and($candidate->subject_entries)->toHaveCount(2)
        ->and($candidate->subject_entries[0]['code'])->toBe('4021')
        ->and($candidate->entry_fee_minor)->toBe(2000);

    expect($f['registration']->fresh()->candidate_count)->toBe(1);
});

it('raises a validation error naming the field when national_registration_no is missing, and blocks export (AC-CMP-01-001)', function (): void {
    $f = cmp01Fixture();
    cmp01ConfirmCandidate($f);
    app(DeriveZimsecCandidatesAction::class)->execute($f['registration']->id);

    $candidate = ZimsecCandidate::where('registration_id', $f['registration']->id)->firstOrFail();
    $candidate->update(['national_registration_no' => null]);

    app(CreateZimsecValidationRuleAction::class)->execute(new CreateZimsecValidationRuleData(
        field: 'national_registration_no', ruleType: 'required', severity: 'error',
        message: 'National registration number is required.',
    ));

    app(ValidateZimsecCandidatesAction::class)->execute($f['registration']->id);

    $candidate->refresh();
    expect($candidate->validation_status)->toBe('errors')
        ->and(collect($candidate->validation_errors)->pluck('field')->all())->toContain('national_registration_no');

    expect(fn () => app(ExportZimsecRegistrationAction::class)->execute(new ExportZimsecRegistrationData(
        registrationId: $f['registration']->id, exportedByUserId: $f['user']->id,
    )))->toThrow(ZimsecExportBlockedException::class);
});

it('flags an A-Level candidate entered for five subjects against the ACA-01 four-subject maximum (AC-CMP-01-004)', function (): void {
    $f = cmp01Fixture();
    SubjectSelectionRule::factory()->for($f['school'])->for($f['framework'], 'framework')->create([
        'rule_type' => 'max_total', 'max_count' => 4, 'severity' => 'block',
        'message' => 'A-Level candidates may enter at most four subjects.',
    ]);

    $extraSubjects = Subject::factory()->for($f['school'])->for($f['framework'], 'framework')->count(3)->create();
    $subjectIds = [$f['english']->id, $f['maths']->id, ...$extraSubjects->pluck('id')->all()];

    cmp01ConfirmCandidate($f, subjectIds: $subjectIds);
    app(DeriveZimsecCandidatesAction::class)->execute($f['registration']->id);
    app(ValidateZimsecCandidatesAction::class)->execute($f['registration']->id);

    $candidate = ZimsecCandidate::where('registration_id', $f['registration']->id)->firstOrFail();
    expect($candidate->validation_status)->toBe('errors')
        ->and(collect($candidate->validation_errors)->pluck('field')->all())->toContain('subject_count');
});

it('bills entry fees through a real FIN-02 ad hoc charge per candidate (BR-CMP-01-006)', function (): void {
    $f = cmp01Fixture();
    cmp01ConfirmCandidate($f);
    app(DeriveZimsecCandidatesAction::class)->execute($f['registration']->id);

    $billed = app(BillZimsecEntryFeesAction::class)->execute(new BillZimsecEntryFeesData(
        registrationId: $f['registration']->id, feeComponentId: $f['feeComponent']->id,
        academicYearId: $f['year']->id, termId: $f['term']->id, raisedByUserId: $f['user']->id,
    ));

    expect($billed)->toHaveCount(1);
    $candidate = $billed->first();
    expect($candidate->ad_hoc_charge_id)->not->toBeNull();
    expect($candidate->adHocCharge->amount_minor)->toBe(2000);
    expect($f['registration']->fresh()->total_fees_minor)->toBe(2000);
});

it('reports a fee shortfall and blocks closure until collections reconcile (AC-CMP-01-002)', function (): void {
    $f = cmp01Fixture();
    $f['registration']->update(['total_fees_minor' => 840000, 'collected_minor' => 795000]);

    expect(fn () => app(CloseZimsecRegistrationAction::class)->execute($f['registration']->id))
        ->toThrow(ZimsecFeeShortfallException::class);

    try {
        app(CloseZimsecRegistrationAction::class)->execute($f['registration']->id);
    } catch (ZimsecFeeShortfallException $e) {
        expect($e->details()['shortfall_minor'])->toBe(45000);
    }

    app(RecordZimsecFeeCollectionAction::class)->execute(new RecordZimsecFeeCollectionData(
        registrationId: $f['registration']->id, amountMinor: 45000, recordedByUserId: $f['user']->id,
    ));

    $closed = app(CloseZimsecRegistrationAction::class)->execute($f['registration']->id);
    expect($closed->status)->toBe('closed');
});

it('refuses export while the centre number is blank (BR-CMP-01-013)', function (): void {
    $f = cmp01Fixture();
    $f['registration']->update(['centre_number' => '']);

    expect(fn () => app(ExportZimsecRegistrationAction::class)->execute(new ExportZimsecRegistrationData(
        registrationId: $f['registration']->id, exportedByUserId: $f['user']->id,
    )))->toThrow(ZimsecExportBlockedException::class);
});

it('exports a validated registration and fires deadline alerts on the configured days, overdue daily (BR-CMP-01-004/008)', function (): void {
    $f = cmp01Fixture();
    cmp01ConfirmCandidate($f);
    app(DeriveZimsecCandidatesAction::class)->execute($f['registration']->id);
    app(ValidateZimsecCandidatesAction::class)->execute($f['registration']->id);

    $exported = app(ExportZimsecRegistrationAction::class)->execute(new ExportZimsecRegistrationData(
        registrationId: $f['registration']->id, exportedByUserId: $f['user']->id,
    ));
    expect($exported->status)->toBe('exported')
        ->and($exported->export_file_id)->not->toBeNull();

    $f['registration']->update(['registration_closes_on' => now()->addDays(7)->toDateString(), 'status' => 'exported']);
    $scan = app(CheckZimsecDeadlinesAction::class)->execute($f['school']->id);
    expect($scan['due']->pluck('id')->all())->toContain($f['registration']->id);

    $f['registration']->update(['registration_closes_on' => now()->subDay()->toDateString()]);
    $scan = app(CheckZimsecDeadlinesAction::class)->execute($f['school']->id);
    expect($scan['overdue']->pluck('id')->all())->toContain($f['registration']->id);
});

it('distributes statements of entry to guardians and tracks confirmation of receipt (BR-CMP-01-009)', function (): void {
    $f = cmp01Fixture();
    $student = Student::factory()->for($f['school'])->create();
    StudentGuardian::factory()->for($f['school'])->for($student)->create();
    cmp01ConfirmCandidate($f, $student);
    app(DeriveZimsecCandidatesAction::class)->execute($f['registration']->id);
    app(ValidateZimsecCandidatesAction::class)->execute($f['registration']->id);

    $distributed = app(DistributeStatementsOfEntryAction::class)->execute($f['registration']->id);
    expect($distributed)->toHaveCount(1);
    $candidate = $distributed->first();
    expect($candidate->statement_of_entry_id)->not->toBeNull()
        ->and($candidate->statement_confirmed)->toBeFalse();

    $confirmed = app(ConfirmStatementOfEntryAction::class)->execute($candidate->id);
    expect($confirmed->statement_confirmed)->toBeTrue();
});

it('imports results by candidate number, writes to student_prior_results, and reports unmatched candidates without dropping them (AC-CMP-01-005)', function (): void {
    $f = cmp01Fixture();
    $student = Student::factory()->for($f['school'])->create();
    cmp01ConfirmCandidate($f, $student);
    app(DeriveZimsecCandidatesAction::class)->execute($f['registration']->id);

    $candidate = ZimsecCandidate::where('registration_id', $f['registration']->id)->firstOrFail();
    $candidate->update(['candidate_number' => 'C0012345']);

    $result = app(ImportZimsecResultsAction::class)->execute(new ImportZimsecResultsData(
        registrationId: $f['registration']->id,
        importedByUserId: $f['user']->id,
        rows: [
            ['candidate_number' => 'C0012345', 'subject_code' => '4021', 'subject_name' => 'English Language', 'grade' => 'B'],
            ['candidate_number' => 'UNKNOWN99', 'subject_code' => '4028', 'subject_name' => 'Mathematics', 'grade' => 'A'],
        ],
    ));

    expect($result->imported)->toHaveCount(1)
        ->and($result->unmatched)->toHaveCount(1)
        ->and($result->unmatched[0]['candidate_number'])->toBe('UNKNOWN99');

    $prior = StudentPriorResult::where('student_id', $student->id)->first();
    expect($prior)->not->toBeNull()
        ->and($prior->grade)->toBe('B')
        ->and($prior->is_verified)->toBeTrue();
});

it('reports pass-rate analysis by subject with historical comparison across series (BR-CMP-01-012)', function (): void {
    $f = cmp01Fixture();
    $studentA = Student::factory()->for($f['school'])->create();
    $studentB = Student::factory()->for($f['school'])->create();

    ZimsecResult::factory()->create([
        'school_id' => $f['school']->id, 'registration_id' => $f['registration']->id, 'student_id' => $studentA->id,
        'subject_code' => '4021', 'subject_name' => 'English Language', 'grade' => 'A',
    ]);
    ZimsecResult::factory()->create([
        'school_id' => $f['school']->id, 'registration_id' => $f['registration']->id, 'student_id' => $studentB->id,
        'subject_code' => '4021', 'subject_name' => 'English Language', 'grade' => 'U',
    ]);

    $analysis = app(AnalyseZimsecPassRatesAction::class)->execute($f['registration']->id);

    expect($analysis->bySubject)->toHaveCount(1)
        ->and($analysis->bySubject[0]['candidates'])->toBe(2)
        ->and($analysis->bySubject[0]['passes'])->toBe(1)
        ->and($analysis->bySubject[0]['pass_rate'])->toBe(50.0);
});
