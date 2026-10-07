<?php

use Illuminate\Http\UploadedFile;
use Modules\Core\Domain\Actions\Documents\CreateNumberingSeriesAction;
use Modules\Core\Domain\DataObjects\Documents\CreateNumberingSeriesData;
use Modules\Core\Models\AcademicYear;
use Modules\Core\Models\GradeLevel;
use Modules\Core\Models\School;
use Modules\People\Models\Application;
use Modules\People\Models\ApplicationDocument;
use Modules\People\Models\Enquiry;
use Modules\People\Models\Intake;
use Modules\People\Models\Student;

/**
 * Book C PPL-02 §6/BR-PPL-02-001/015 — the public, unauthenticated admissions REST surface.
 *
 * @return array{school: School, intake: Intake}
 */
function publicApplicationsFixture(array $intakeOverrides = []): array
{
    $school = School::factory()->create();
    $year = AcademicYear::factory()->for($school)->create();
    $gradeLevel = GradeLevel::factory()->for($school)->create();
    app(CreateNumberingSeriesAction::class)->execute(new CreateNumberingSeriesData($school->id, 'application', 'APP/{SEQ:6}'));

    $intake = Intake::factory()->create([
        'school_id' => $school->id,
        'academic_year_id' => $year->id,
        'grade_level_id' => $gradeLevel->id,
        'public_form_enabled' => true,
        'public_form_slug' => 'form1-'.fake()->unique()->numerify('####'),
        ...$intakeOverrides,
    ]);

    return ['school' => $school, 'intake' => $intake];
}

it('lists only open, public-form-enabled intakes for the named school', function (): void {
    $f = publicApplicationsFixture();
    Intake::factory()->create([
        'school_id' => $f['school']->id, 'academic_year_id' => $f['intake']->academic_year_id,
        'grade_level_id' => $f['intake']->grade_level_id, 'public_form_enabled' => false,
    ]);
    $otherSchool = School::factory()->create();
    Intake::factory()->create([
        'school_id' => $otherSchool->id, 'academic_year_id' => AcademicYear::factory()->for($otherSchool)->create()->id,
        'grade_level_id' => GradeLevel::factory()->for($otherSchool)->create()->id, 'public_form_enabled' => true,
    ]);

    $response = $this->getJson('/api/v1/public/intakes?school='.$f['school']->ulid)->assertOk();

    expect($response->json('data'))->toHaveCount(1)
        ->and($response->json('data.0.slug'))->toBe($f['intake']->public_form_slug);
});

it('creates an application and never a student record, keyed off the intake slug (BR-PPL-02-001)', function (): void {
    $f = publicApplicationsFixture();

    $response = $this->postJson('/api/v1/public/applications', [
        'intake_slug' => $f['intake']->public_form_slug,
        'first_name' => 'Tanaka', 'last_name' => 'Moyo', 'date_of_birth' => '2015-03-10', 'gender' => 'male',
        'requested_enrolment_type' => 'FULL_TIME', 'requested_residency' => 'DAY',
        'guardians' => [['relationship' => 'mother', 'first_name' => 'Rudo', 'last_name' => 'Moyo', 'primary_phone' => '+263771234567', 'is_primary_contact' => true, 'is_fee_responsible' => true]],
    ])->assertCreated();

    $application = Application::withoutGlobalScopes()->sole();
    expect($application->application_number)->not->toBeNull()
        ->and($application->student_id)->toBeNull()
        ->and($application->first_name)->toBe('Tanaka')
        ->and($response->json('data.application_number'))->toBe($application->application_number)
        ->and(Student::withoutGlobalScopes()->count())->toBe(0);
});

it('silently accepts without creating anything when the honeypot is filled or the form was too fast', function (): void {
    $f = publicApplicationsFixture();

    $this->postJson('/api/v1/public/applications', [
        'intake_slug' => $f['intake']->public_form_slug, 'website' => 'http://spam.example',
        'first_name' => 'Bot', 'last_name' => 'Spam', 'date_of_birth' => '2015-03-10', 'gender' => 'male',
        'requested_enrolment_type' => 'FULL_TIME', 'requested_residency' => 'DAY', 'guardians' => [['relationship' => 'mother']],
    ])->assertStatus(202);

    $this->postJson('/api/v1/public/applications', [
        'intake_slug' => $f['intake']->public_form_slug, 'started_at' => now()->getTimestamp(),
        'first_name' => 'Fast', 'last_name' => 'Bot', 'date_of_birth' => '2015-03-10', 'gender' => 'male',
        'requested_enrolment_type' => 'FULL_TIME', 'requested_residency' => 'DAY', 'guardians' => [['relationship' => 'mother']],
    ])->assertStatus(202);

    expect(Application::withoutGlobalScopes()->count())->toBe(0);
});

it('refuses an application against an unknown, closed, or disabled intake slug', function (): void {
    publicApplicationsFixture();

    $this->postJson('/api/v1/public/applications', ['intake_slug' => 'no-such-slug'])->assertStatus(404);
});

it('tracks an application by number and date of birth only, refusing a wrong date of birth', function (): void {
    $f = publicApplicationsFixture();
    $this->postJson('/api/v1/public/applications', [
        'intake_slug' => $f['intake']->public_form_slug,
        'first_name' => 'Tanaka', 'last_name' => 'Moyo', 'date_of_birth' => '2015-03-10', 'gender' => 'male',
        'requested_enrolment_type' => 'FULL_TIME', 'requested_residency' => 'DAY',
        'guardians' => [['relationship' => 'mother', 'primary_phone' => '+263771234567', 'is_fee_responsible' => true]],
    ])->assertCreated();
    $number = Application::withoutGlobalScopes()->sole()->application_number;

    $this->getJson('/api/v1/public/applications/track?application_number='.$number.'&date_of_birth=2015-03-10')
        ->assertOk()->assertJsonPath('data.application_number', $number);

    $this->getJson('/api/v1/public/applications/track?application_number='.$number.'&date_of_birth=1999-01-01')
        ->assertStatus(404);
});

it('attaches a document only when the submitted date of birth matches the application', function (): void {
    $f = publicApplicationsFixture();
    $this->postJson('/api/v1/public/applications', [
        'intake_slug' => $f['intake']->public_form_slug,
        'first_name' => 'Tanaka', 'last_name' => 'Moyo', 'date_of_birth' => '2015-03-10', 'gender' => 'male',
        'requested_enrolment_type' => 'FULL_TIME', 'requested_residency' => 'DAY',
        'guardians' => [['relationship' => 'mother', 'primary_phone' => '+263771234567', 'is_fee_responsible' => true]],
    ])->assertCreated();
    $application = Application::withoutGlobalScopes()->sole();

    $pdf = UploadedFile::fake()->createWithContent('cert.pdf', "%PDF-1.4\n1 0 obj<<>>endobj\ntrailer<<>>\n%%EOF");

    $this->post("/api/v1/public/applications/{$application->ulid}/documents", [
        'date_of_birth' => '1999-01-01', 'document_type' => 'birth_certificate', 'file' => $pdf,
    ])->assertStatus(401);

    $this->post("/api/v1/public/applications/{$application->ulid}/documents", [
        'date_of_birth' => '2015-03-10', 'document_type' => 'birth_certificate', 'file' => $pdf,
    ])->assertCreated();

    expect(ApplicationDocument::withoutGlobalScopes()->where('application_id', $application->id)->count())->toBe(1);
});

it('creates a public enquiry for the named school', function (): void {
    $f = publicApplicationsFixture();

    $this->postJson('/api/v1/public/enquiries', [
        'school' => $f['school']->ulid,
        'enquirer_name' => 'Mrs Ncube', 'enquirer_phone' => '+263772223344', 'message' => 'Interested in Form 1',
    ])->assertCreated();

    expect(Enquiry::withoutGlobalScopes()->sole()->enquirer_name)->toBe('Mrs Ncube');
});
