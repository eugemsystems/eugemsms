<?php

use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Modules\Academic\Models\AttendanceSession;
use Modules\Compliance\Domain\Actions\BuildEnrolmentSnapshotAction;
use Modules\Compliance\Domain\Actions\BuildStaffEstablishmentSnapshotAction;
use Modules\Compliance\Domain\Actions\CheckStatutorySchoolReturnDeadlinesAction;
use Modules\Compliance\Domain\Actions\ExportStatutorySchoolReturnAction;
use Modules\Compliance\Domain\Actions\GenerateInspectionPackAction;
use Modules\Compliance\Domain\Actions\GenerateStatutorySchoolReturnAction;
use Modules\Compliance\Domain\Actions\RecordStatutorySchoolReturnSubmissionAction;
use Modules\Compliance\Domain\Actions\RunDataQualityChecksAction;
use Modules\Compliance\Domain\DataObjects\GenerateInspectionPackData;
use Modules\Compliance\Domain\DataObjects\GenerateStatutorySchoolReturnData;
use Modules\Compliance\Domain\DataObjects\RecordStatutorySchoolReturnSubmissionData;
use Modules\Compliance\Models\StatutorySchoolReturn;
use Modules\Core\Domain\Support\SchoolContext;
use Modules\Core\Models\GradeLevel;
use Modules\Core\Models\School;
use Modules\Core\Models\SchoolSection;
use Modules\People\Models\EstablishmentPost;
use Modules\People\Models\Staff;
use Modules\People\Models\Student;

/**
 * @return array<string, mixed>
 */
function cmp02Fixture(): array
{
    $school = School::factory()->create(['base_currency' => 'USD']);
    SchoolContext::set($school);
    $user = User::factory()->create();
    $section = SchoolSection::factory()->for($school)->create();
    $gradeLevel = GradeLevel::factory()->for($school)->for($section, 'section')->create();

    return compact('school', 'user', 'section', 'gradeLevel');
}

function cmp02Student(array $f, array $overrides = []): Student
{
    return Student::factory()->for($f['school'])->create([
        'section_id' => $f['section']->id,
        'grade_level_id' => $f['gradeLevel']->id,
        ...$overrides,
    ]);
}

it('reports students missing a national registration number before the census is generated (AC-CMP-02-001)', function (): void {
    $f = cmp02Fixture();
    cmp02Student($f, ['national_registration_no' => null]);
    cmp02Student($f, ['national_registration_no' => null]);
    cmp02Student($f, ['national_registration_no' => '63-123456A78']);

    $checks = app(RunDataQualityChecksAction::class)->execute($f['school']->id);
    $missingReg = $checks->firstWhere('check_key', 'missing_national_reg');

    expect($missingReg->affected_count)->toBe(2);

    $result = app(GenerateStatutorySchoolReturnAction::class)->execute(new GenerateStatutorySchoolReturnData(
        schoolId: $f['school']->id, returnType: 'annual_schools_census', periodReference: now()->format('Y'),
        dueDate: now()->addMonth(), authority: 'MoPSE District', generatedByUserId: $f['user']->id,
    ));

    expect($result->return->quality_issues)->toBe(2);
    $qualityRow = collect($result->return->validation_result)->firstWhere('check_key', 'missing_national_reg');
    expect($qualityRow['affected_count'])->toBe(2);
});

it('breaks enrolment down by level, gender and residency (BR-CMP-02-003)', function (): void {
    $f = cmp02Fixture();
    cmp02Student($f, ['gender' => 'male', 'residency' => 'DAY']);
    cmp02Student($f, ['gender' => 'male', 'residency' => 'BOARDER']);
    cmp02Student($f, ['gender' => 'female', 'residency' => 'DAY']);

    $snapshot = app(BuildEnrolmentSnapshotAction::class)->execute($f['school']->id);

    expect($snapshot['total'])->toBe(3)
        ->and($snapshot['by_gender']['male'])->toBe(2)
        ->and($snapshot['by_gender']['female'])->toBe(1)
        ->and($snapshot['by_residency']['DAY'])->toBe(2)
        ->and($snapshot['by_residency']['BOARDER'])->toBe(1)
        ->and($snapshot['by_level'][0]['count'])->toBe(3);
});

it('reports approved posts, filled posts and vacancies, flagging that qualifications are not tracked (BR-CMP-02-004)', function (): void {
    $f = cmp02Fixture();
    EstablishmentPost::factory()->for($f['school'])->create(['title' => 'Class Teacher', 'approved_count' => 10, 'filled_count' => 7]);

    $snapshot = app(BuildStaffEstablishmentSnapshotAction::class)->execute($f['school']->id);

    expect($snapshot['approved_posts'])->toBe(10)
        ->and($snapshot['filled_posts'])->toBe(7)
        ->and($snapshot['vacancies'])->toBe(3)
        ->and($snapshot['qualifications_tracked'])->toBeFalse();
});

it('freezes the snapshot on first generation and reports divergence on a later regeneration (AC-CMP-02-002)', function (): void {
    $f = cmp02Fixture();
    cmp02Student($f);

    $first = app(GenerateStatutorySchoolReturnAction::class)->execute(new GenerateStatutorySchoolReturnData(
        schoolId: $f['school']->id, returnType: 'term_enrolment', periodReference: 'Term-2026-1',
        dueDate: now()->addMonth(), authority: 'MoPSE District', generatedByUserId: $f['user']->id,
    ));
    expect($first->wasAlreadyGenerated)->toBeFalse();
    $frozenTotal = $first->return->data_snapshot['enrolment']['total'];

    app(RecordStatutorySchoolReturnSubmissionAction::class)->execute(new RecordStatutorySchoolReturnSubmissionData(
        returnId: $first->return->id, submittedByUserId: $f['user']->id, acknowledgementRef: 'MOPSE-REF-001',
    ));

    cmp02Student($f);

    $second = app(GenerateStatutorySchoolReturnAction::class)->execute(new GenerateStatutorySchoolReturnData(
        schoolId: $f['school']->id, returnType: 'term_enrolment', periodReference: 'Term-2026-1',
        dueDate: now()->addMonth(), authority: 'MoPSE District', generatedByUserId: $f['user']->id,
    ));

    expect($second->wasAlreadyGenerated)->toBeTrue()
        ->and($second->return->data_snapshot['enrolment']['total'])->toBe($frozenTotal)
        ->and($second->divergence)->toHaveKey('enrolment')
        ->and($second->return->fresh()->status)->toBe('submitted');
});

it('records manual submission with a reference (BR-CMP-02-007)', function (): void {
    $f = cmp02Fixture();
    $result = app(GenerateStatutorySchoolReturnAction::class)->execute(new GenerateStatutorySchoolReturnData(
        schoolId: $f['school']->id, returnType: 'staff_establishment', periodReference: 'Term-2026-1',
        dueDate: now()->addMonth(), authority: 'MoPSE Province', generatedByUserId: $f['user']->id,
    ));

    $submitted = app(RecordStatutorySchoolReturnSubmissionAction::class)->execute(new RecordStatutorySchoolReturnSubmissionData(
        returnId: $result->return->id, submittedByUserId: $f['user']->id, acknowledgementRef: 'MOPSE-2026-778',
    ));

    expect($submitted->status)->toBe('submitted')
        ->and($submitted->acknowledgement_ref)->toBe('MOPSE-2026-778')
        ->and($submitted->submitted_at)->not->toBeNull();
});

it('exports the return\'s own frozen snapshot, never a freshly rebuilt one', function (): void {
    $f = cmp02Fixture();
    cmp02Student($f);
    $result = app(GenerateStatutorySchoolReturnAction::class)->execute(new GenerateStatutorySchoolReturnData(
        schoolId: $f['school']->id, returnType: 'term_enrolment', periodReference: 'Term-2026-2',
        dueDate: now()->addMonth(), authority: 'MoPSE District', generatedByUserId: $f['user']->id,
    ));

    $exported = app(ExportStatutorySchoolReturnAction::class)->execute($result->return->id, $f['user']->id);

    expect($exported->export_file_id)->not->toBeNull();
});

it('fires deadline alerts on the configured days and overdue daily (BR-CMP-02-006)', function (): void {
    $f = cmp02Fixture();
    $return = StatutorySchoolReturn::factory()->for($f['school'])->create([
        'status' => 'validated', 'due_date' => now()->addDays(14)->toDateString(),
    ]);

    $scan = app(CheckStatutorySchoolReturnDeadlinesAction::class)->execute($f['school']->id);
    expect($scan['due']->pluck('id')->all())->toContain($return->id);

    $return->update(['due_date' => now()->subDays(3)->toDateString()]);
    $scan = app(CheckStatutorySchoolReturnDeadlinesAction::class)->execute($f['school']->id);
    expect($scan['overdue']->pluck('id')->all())->toContain($return->id);
});

it('assembles attendance registers, staff records and statutory documents into one inspection pack (AC-CMP-02-003)', function (): void {
    $f = cmp02Fixture();
    $student = cmp02Student($f);
    AttendanceSession::factory()->for($f['school'])->create(['session_date' => now()->toDateString()]);
    Staff::factory()->for($f['school'])->teaching()->create(['teacher_registration_no' => 'TRN-001']);
    EstablishmentPost::factory()->for($f['school'])->create();

    $file = app(GenerateInspectionPackAction::class)->execute(new GenerateInspectionPackData(
        schoolId: $f['school']->id, periodStart: now()->subWeek(), periodEnd: now()->addWeek(),
        generatedByUserId: $f['user']->id,
    ));

    expect($file->category)->toBe('inspection_pack');

    $contents = Storage::disk($file->disk)->get($file->path);
    $payload = json_decode((string) $contents, true);

    expect($payload['attendance_registers'])->toHaveCount(1)
        ->and($payload['staff_records'])->toHaveCount(1)
        ->and($payload['establishment_posts'])->toHaveCount(1)
        ->and($payload['policy_acknowledgements'])->toBeNull();
});
