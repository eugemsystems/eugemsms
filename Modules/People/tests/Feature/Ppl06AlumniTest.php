<?php

use App\Models\User;
use Modules\Academic\Models\TermResult;
use Modules\Core\Domain\Actions\Documents\CreateNumberingSeriesAction;
use Modules\Core\Domain\DataObjects\Documents\CreateNumberingSeriesData;
use Modules\Core\Domain\Support\Auth\UserType;
use Modules\Core\Domain\Support\SchoolContext;
use Modules\Core\Models\AcademicYear;
use Modules\Core\Models\GradeLevel;
use Modules\Core\Models\NotificationOptOut;
use Modules\Core\Models\School;
use Modules\Core\Models\SchoolSection;
use Modules\Core\Models\Term;
use Modules\Finance\Domain\Actions\CreateDiscountSchemeAction;
use Modules\Finance\Domain\DataObjects\CreateDiscountSchemeData;
use Modules\Finance\Models\Account;
use Modules\Finance\Models\SchemeBudgetEnvelope;
use Modules\People\Domain\Actions\ChangeStudentStatusAction;
use Modules\People\Domain\Actions\CreateAlumniEventAction;
use Modules\People\Domain\Actions\CreateBursaryEndowmentAction;
use Modules\People\Domain\Actions\CreateCapitalCampaignAction;
use Modules\People\Domain\Actions\CreatePledgeAction;
use Modules\People\Domain\Actions\CreateStudentAction;
use Modules\People\Domain\Actions\OfferAlumniPortalAccountAction;
use Modules\People\Domain\Actions\OptOutAlumniContactAction;
use Modules\People\Domain\Actions\RecordCareerUpdateAction;
use Modules\People\Domain\Actions\RecordDonationAction;
use Modules\People\Domain\Actions\VerifyCareerUpdateAction;
use Modules\People\Domain\DataObjects\ChangeStudentStatusData;
use Modules\People\Domain\DataObjects\CreateAlumniEventData;
use Modules\People\Domain\DataObjects\CreateBursaryEndowmentData;
use Modules\People\Domain\DataObjects\CreateCapitalCampaignData;
use Modules\People\Domain\DataObjects\CreatePledgeData;
use Modules\People\Domain\DataObjects\CreateStudentData;
use Modules\People\Domain\DataObjects\OfferAlumniPortalAccountData;
use Modules\People\Domain\DataObjects\OptOutAlumniContactData;
use Modules\People\Domain\DataObjects\RecordCareerUpdateData;
use Modules\People\Domain\DataObjects\RecordDonationData;
use Modules\People\Domain\DataObjects\VerifyCareerUpdateData;
use Modules\People\Models\Alumnus;
use Modules\People\Models\Student;
use Modules\Sport\Models\Award;

/**
 * @return array{school: School, year: AcademicYear, term: Term, section: SchoolSection, gradeLevel: GradeLevel, user: User}
 */
function ppl06Fixture(): array
{
    $school = School::factory()->create();
    SchoolContext::set($school);
    $year = AcademicYear::factory()->for($school)->create(['is_current' => true]);
    $term = Term::factory()->for($school)->for($year, 'academicYear')->create(['is_current' => true]);
    $section = SchoolSection::factory()->for($school)->create();
    $gradeLevel = GradeLevel::factory()->for($school)->for($section, 'section')->create();
    $user = User::factory()->create();

    app(CreateNumberingSeriesAction::class)->execute(new CreateNumberingSeriesData(
        schoolId: $school->id, documentType: 'admission', pattern: '{SCHOOL}/{YEAR}/{SEQ:4}', academicYearId: $year->id,
    ));
    app(CreateNumberingSeriesAction::class)->execute(new CreateNumberingSeriesData(
        schoolId: $school->id, documentType: 'journal', pattern: 'JNL/{SEQ:6}',
    ));

    return ['school' => $school, 'year' => $year, 'term' => $term, 'section' => $section, 'gradeLevel' => $gradeLevel, 'user' => $user];
}

/**
 * @param  array<string, mixed>  $f
 */
function ppl06GraduatingStudent(array $f): Student
{
    $student = app(CreateStudentAction::class)->execute(new CreateStudentData(
        schoolId: $f['school']->id, academicYearId: $f['year']->id, termId: $f['term']->id,
        firstName: 'Tanaka', lastName: 'Moyo', dateOfBirth: now()->subYears(18), gender: 'male',
        enrolmentType: 'FULL_TIME', residency: 'DAY', sectionId: $f['section']->id, gradeLevelId: $f['gradeLevel']->id,
        entryCohortYear: (int) now()->subYears(6)->year, createdByUserId: $f['user']->id, skipDuplicateCheck: true,
    ));

    app(ChangeStudentStatusAction::class)->execute(new ChangeStudentStatusData(
        studentId: $student->id, newStatus: 'active', changedByUserId: $f['user']->id,
    ));

    return $student->fresh();
}

it('automatically creates an alumni record on graduation with a frozen academic summary and honours (AC-PPL-06-001)', function (): void {
    $f = ppl06Fixture();
    $student = ppl06GraduatingStudent($f);

    TermResult::factory()->create([
        'school_id' => $f['school']->id, 'academic_year_id' => $f['year']->id, 'term_id' => $f['term']->id,
        'student_id' => $student->id, 'average_percent' => '78.50', 'class_position' => 2, 'class_size' => 30,
    ]);
    Award::factory()->create([
        'school_id' => $f['school']->id, 'academic_year_id' => $f['year']->id, 'student_id' => $student->id,
        'award_type' => 'full_colours', 'title' => 'Full Colours — Athletics',
    ]);

    app(ChangeStudentStatusAction::class)->execute(new ChangeStudentStatusData(
        studentId: $student->id, newStatus: 'graduated', changedByUserId: $f['user']->id,
    ));

    $alumnus = Alumnus::where('student_id', $student->id)->firstOrFail();

    expect($alumnus->admission_number)->toBe($student->admission_number)
        ->and($alumnus->academic_summary_snapshot['final_average_percent'])->toBe(78.5)
        ->and($alumnus->academic_summary_snapshot['honours'])->toHaveCount(1)
        ->and($alumnus->academic_summary_snapshot['honours'][0]['title'])->toBe('Full Colours — Athletics')
        ->and($alumnus->status)->toBe('active');
});

it('keeps the frozen academic summary unchanged even after the underlying term result is later revised (AC-PPL-06-002)', function (): void {
    $f = ppl06Fixture();
    $student = ppl06GraduatingStudent($f);

    $result = TermResult::factory()->create([
        'school_id' => $f['school']->id, 'academic_year_id' => $f['year']->id, 'term_id' => $f['term']->id,
        'student_id' => $student->id, 'average_percent' => '78.50',
    ]);

    app(ChangeStudentStatusAction::class)->execute(new ChangeStudentStatusData(
        studentId: $student->id, newStatus: 'graduated', changedByUserId: $f['user']->id,
    ));

    $alumnus = Alumnus::where('student_id', $student->id)->firstOrFail();
    expect($alumnus->academic_summary_snapshot['final_average_percent'])->toBe(78.5);

    // Simulates the school's grading scale (or a later remark) changing the
    // live term result years afterward — the frozen snapshot must not move.
    $result->update(['average_percent' => '55.00']);

    expect($alumnus->fresh()->academic_summary_snapshot['final_average_percent'])->toBe(78.5);
});

it('reflects actual donations received on a pledge, not the pledged amount (AC-PPL-06-003)', function (): void {
    $f = ppl06Fixture();
    $incomeAccount = Account::factory()->for($f['school'])->income()->create();
    $bankAccount = Account::factory()->for($f['school'])->create();

    $campaign = app(CreateCapitalCampaignAction::class)->execute(new CreateCapitalCampaignData(
        schoolId: $f['school']->id, name: 'New Science Block Appeal', purpose: 'Fund a new science block.',
        targetAmountMinor: 10_000_000, currency: 'USD', startsOn: now(), incomeAccountId: $incomeAccount->id,
    ));
    $pledge = app(CreatePledgeAction::class)->execute(new CreatePledgeData(
        schoolId: $f['school']->id, donorName: 'Jane Alumna', donorType: 'alumnus',
        pledgedAmountMinor: 500_000, currency: 'USD', campaignId: $campaign->id,
    ));

    app(RecordDonationAction::class)->execute(new RecordDonationData(
        schoolId: $f['school']->id, academicYearId: $f['year']->id, termId: $f['term']->id,
        donorName: 'Jane Alumna', amountMinor: 120_000, currency: 'USD', bankAccountId: $bankAccount->id,
        recordedByUserId: $f['user']->id, campaignId: $campaign->id, pledgeId: $pledge->id,
    ));

    expect($campaign->fresh()->raised_amount_minor)->toBe(120_000)
        ->and($pledge->fresh()->paid_to_date_minor)->toBe(120_000)
        ->and($pledge->fresh()->status)->toBe('fulfilling');
});

it('exhausts FIN-07\'s own budget-envelope refusal once an endowment\'s available balance runs out, and restores capacity once topped up (AC-PPL-06-004)', function (): void {
    $f = ppl06Fixture();
    $scheme = app(CreateDiscountSchemeAction::class)->execute(new CreateDiscountSchemeData(
        schoolId: $f['school']->id, code: 'MOYO', name: 'The Moyo Family Bursary', schemeType: 'manual',
        category: 'means_tested', calculationMethod: 'percentage', contraAccountId: Account::factory()->for($f['school'])->create()->id,
        requiresApproval: false,
    ));

    $endowment = app(CreateBursaryEndowmentAction::class)->execute(new CreateBursaryEndowmentData(
        schoolId: $f['school']->id, donorName: 'The Moyo Family', currency: 'USD', fundsSchemeId: $scheme->id, startsOn: now(),
        endowmentCapitalMinor: 100_000,
    ));

    $envelope = SchemeBudgetEnvelope::where('scheme_id', $scheme->id)
        ->where('academic_year_id', $f['year']->id)->firstOrFail();
    expect($envelope->budget_minor)->toBe(100_000);

    // The scheme has already committed the endowment's full capital —
    // FIN-07's OWN wouldExceed() must now refuse any further commitment,
    // completely unchanged by PPL-06.
    $envelope->update(['committed_minor' => 100_000]);
    expect($envelope->fresh()->wouldExceed(1))->toBeTrue();

    // Topping up the endowment restores real capacity.
    $bankAccount = Account::factory()->for($f['school'])->create();
    $donationIncomeAccount = Account::factory()->for($f['school'])->income()->create();
    app(RecordDonationAction::class)->execute(new RecordDonationData(
        schoolId: $f['school']->id, academicYearId: $f['year']->id, termId: $f['term']->id,
        donorName: 'The Moyo Family', amountMinor: 50_000, currency: 'USD', bankAccountId: $bankAccount->id,
        incomeAccountId: $donationIncomeAccount->id,
        recordedByUserId: $f['user']->id, bursaryEndowmentId: $endowment->id,
    ));

    $envelope = $envelope->fresh();
    expect($envelope->budget_minor)->toBe(150_000)
        ->and($envelope->wouldExceed(1))->toBeFalse();
});

it('suppresses further outreach once an alumnus opts out, through the real CORE-09 opt-out mechanism (AC-PPL-06-005)', function (): void {
    $f = ppl06Fixture();
    $student = ppl06GraduatingStudent($f);
    app(ChangeStudentStatusAction::class)->execute(new ChangeStudentStatusData(
        studentId: $student->id, newStatus: 'graduated', changedByUserId: $f['user']->id,
    ));
    $alumnus = Alumnus::where('student_id', $student->id)->firstOrFail();

    $offered = app(OfferAlumniPortalAccountAction::class)->execute(new OfferAlumniPortalAccountData(
        alumnusId: $alumnus->id, email: 'tanaka.moyo@example.com',
    ));

    $optedOut = app(OptOutAlumniContactAction::class)->execute(new OptOutAlumniContactData(
        alumnusId: $offered->id, reason: 'No longer wishes to be contacted.',
    ));

    expect($optedOut->status)->toBe('opted_out');
    expect(NotificationOptOut::where('school_id', $f['school']->id)->where('address', 'tanaka.moyo@example.com')->where('channel', 'email')->exists())->toBeTrue();
});

it('creates a genuinely new, narrowly-scoped alumni portal identity distinct from the closed learner account (AC-PPL-06-006)', function (): void {
    $f = ppl06Fixture();
    $student = ppl06GraduatingStudent($f);
    expect($student->user_id)->toBeNull();

    app(ChangeStudentStatusAction::class)->execute(new ChangeStudentStatusData(
        studentId: $student->id, newStatus: 'graduated', changedByUserId: $f['user']->id,
    ));
    $alumnus = Alumnus::where('student_id', $student->id)->firstOrFail();

    $offered = app(OfferAlumniPortalAccountAction::class)->execute(new OfferAlumniPortalAccountData(
        alumnusId: $alumnus->id, email: 'tanaka.moyo@example.com',
    ));

    $portalUser = User::findOrFail($offered->user_id);

    // A full request-time authorization check that an alumnus token is
    // refused on current-learner endpoints is an HTTP/API-layer concern
    // this backend-only pass doesn't build (see the Action's own
    // docblock) — this asserts the structural half: a distinct
    // UserType::Alumni identity, never written back onto the closed
    // learner record.
    expect($portalUser->user_type)->toBe(UserType::Alumni)
        ->and($student->fresh()->user_id)->toBeNull();
});

it('keeps a self-reported career update unverified until the school confirms it (BR-PPL-06-004)', function (): void {
    $f = ppl06Fixture();
    $student = ppl06GraduatingStudent($f);
    app(ChangeStudentStatusAction::class)->execute(new ChangeStudentStatusData(
        studentId: $student->id, newStatus: 'graduated', changedByUserId: $f['user']->id,
    ));
    $alumnus = Alumnus::where('student_id', $student->id)->firstOrFail();

    $update = app(RecordCareerUpdateAction::class)->execute(new RecordCareerUpdateData(
        alumnusId: $alumnus->id, updateType: 'employment', title: 'Software Engineer',
        institutionOrEmployer: 'Example Corp', isCurrent: true,
    ));
    expect($update->verified)->toBeFalse();

    $verified = app(VerifyCareerUpdateAction::class)->execute(new VerifyCareerUpdateData(careerUpdateId: $update->id));
    expect($verified->verified)->toBeTrue();
});

it('builds an alumni event entirely on top of COM-06\'s own calendar (BR-PPL-06-005)', function (): void {
    $f = ppl06Fixture();

    $event = app(CreateAlumniEventAction::class)->execute(new CreateAlumniEventData(
        schoolId: $f['school']->id, academicYearId: $f['year']->id, title: 'Class of 2015 Reunion',
        startsAt: now()->addMonths(2), eventType: 'reunion', targetGraduationYears: [2015],
    ));

    expect($event->calendarEvent)->not->toBeNull()
        ->and($event->calendarEvent->title)->toBe('Class of 2015 Reunion')
        ->and($event->targetsGraduationYear(2015))->toBeTrue()
        ->and($event->targetsGraduationYear(2010))->toBeFalse();
});
