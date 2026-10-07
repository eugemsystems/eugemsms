<?php

use App\Models\User;
use Laravel\Sanctum\Sanctum;
use Modules\Academic\Models\Subject;
use Modules\Boarding\Models\Exeat;
use Modules\Boarding\Models\ExeatType;
use Modules\Core\Domain\Support\SchoolContext;
use Modules\Core\Domain\Support\SessionContext;
use Modules\Core\Models\AcademicYear;
use Modules\Core\Models\School;
use Modules\Core\Models\SchoolClass;
use Modules\Core\Models\Term;
use Modules\People\Domain\Actions\LinkGuardianToStudentAction;
use Modules\People\Domain\Actions\RecordStudentTimelineEventAction;
use Modules\People\Domain\DataObjects\LinkGuardianToStudentData;
use Modules\People\Domain\DataObjects\RecordStudentTimelineEventData;
use Modules\People\Models\DutyAssignment;
use Modules\People\Models\DutyRoster;
use Modules\People\Models\FeeLiability;
use Modules\People\Models\Guardian;
use Modules\People\Models\LeaveBalance;
use Modules\People\Models\LeaveType;
use Modules\People\Models\Staff;
use Modules\People\Models\StaffWorkload;
use Modules\People\Models\Student;
use Modules\People\Models\TeacherAllocation;

/**
 * Book C's own `/api/v1` surface (PPL-01 §9, PPL-03 §8, PPL-04 §6) — the fourth and last of the
 * four gaps found when `PROGRESS.md`'s stale Book C status was corrected on 2026-10-07.
 *
 * @return array{school: School, year: AcademicYear, term: Term, user: User}
 */
function apiSurfaceFixture(): array
{
    $school = School::factory()->create();
    SchoolContext::set($school);
    $year = AcademicYear::factory()->for($school)->current()->create();
    $term = Term::factory()->for($school)->for($year, 'academicYear')->current()->create();
    SessionContext::set($year, $term);
    $user = User::factory()->create();
    $user->schools()->attach($school, ['status' => 'active']);

    return ['school' => $school, 'year' => $year, 'term' => $term, 'user' => $user];
}

// --- StudentsController (staff-scoped) -------------------------------------------------------

it('lists and searches students by admission number or name', function (): void {
    $f = apiSurfaceFixture();
    $match = Student::factory()->for($f['school'])->create(['first_name' => 'Tanaka', 'last_name' => 'Moyo']);
    Student::factory()->for($f['school'])->create(['first_name' => 'Rudo', 'last_name' => 'Chikosi']);
    Sanctum::actingAs($f['user'], ['*']);

    $response = $this->getJson('/api/v1/students?q=Tanaka')->assertOk();

    expect($response->json('data'))->toHaveCount(1)
        ->and($response->json('data.0.id'))->toBe($match->ulid);
});

it('shows a single student profile, their timeline, enrolments and guardians', function (): void {
    $f = apiSurfaceFixture();
    $student = Student::factory()->for($f['school'])->create();
    $guardian = Guardian::factory()->for($f['school'])->create();
    app(LinkGuardianToStudentAction::class)->execute(new LinkGuardianToStudentData(
        studentId: $student->id, guardianId: $guardian->id, relationship: 'mother', createdByUserId: $f['user']->id,
        isPrimaryContact: true, isFeeResponsible: true, mayCollectLearner: true,
    ));
    app(RecordStudentTimelineEventAction::class)->execute(new RecordStudentTimelineEventData(
        schoolId: $f['school']->id, studentId: $student->id, eventCategory: 'academic', eventType: 'note', title: 'Settled in well',
    ));
    Sanctum::actingAs($f['user'], ['*']);

    $this->getJson("/api/v1/students/{$student->ulid}")->assertOk()->assertJsonPath('data.admission_number', $student->admission_number);

    $this->getJson("/api/v1/students/{$student->ulid}/timeline")->assertOk()->assertJsonPath('data.0.title', 'Settled in well');

    $this->getJson("/api/v1/students/{$student->ulid}/guardians")->assertOk()
        ->assertJsonPath('data.0.guardian_id', $guardian->ulid)
        ->assertJsonPath('data.0.may_collect_learner', true);

    $this->getJson("/api/v1/students/{$student->ulid}/enrolments")->assertOk()->assertJsonCount(0, 'data');
});

it('refuses collection without an approved current exeat, and releases when one exists and the guardian has the right', function (): void {
    $f = apiSurfaceFixture();
    $student = Student::factory()->for($f['school'])->create();
    $guardian = Guardian::factory()->for($f['school'])->create();
    app(LinkGuardianToStudentAction::class)->execute(new LinkGuardianToStudentData(
        studentId: $student->id, guardianId: $guardian->id, relationship: 'mother', createdByUserId: $f['user']->id,
        isPrimaryContact: true, isFeeResponsible: true, mayCollectLearner: true,
    ));
    Sanctum::actingAs($f['user'], ['*']);

    $this->getJson("/api/v1/students/{$student->ulid}/collection-authorised?name=".urlencode($guardian->displayName())."&guardian_id={$guardian->ulid}")
        ->assertOk()->assertJsonPath('data.released', false)->assertJsonPath('data.refusal_reason', 'no_exeat');

    $exeatType = ExeatType::factory()->for($f['school'])->create();
    Exeat::query()->create([
        'school_id' => $f['school']->id, 'academic_year_id' => $f['year']->id, 'term_id' => $f['term']->id,
        'exeat_number' => 'EX-000001', 'student_id' => $student->id, 'exeat_type_id' => $exeatType->id,
        'request_source' => 'guardian_portal', 'reason' => 'Weekend visit', 'departs_at' => now(), 'returns_by' => now()->addDays(2),
        'destination_address' => '12 Baines Ave', 'destination_country' => 'ZW', 'contact_phone' => '+263771234567',
        'collecting_guardian_id' => $guardian->id, 'collection_method' => 'guardian_collect', 'status' => 'approved',
    ]);

    $this->getJson("/api/v1/students/{$student->ulid}/collection-authorised?name=".urlencode($guardian->displayName())."&guardian_id={$guardian->ulid}&identity_verified=1")
        ->assertOk()->assertJsonPath('data.released', true);
});

// --- LearnerProfileController -----------------------------------------------------------------

it('shows a learner token its own reduced profile', function (): void {
    $f = apiSurfaceFixture();
    $learnerUser = User::factory()->create();
    $learnerUser->schools()->attach($f['school'], ['status' => 'active']);
    $student = Student::factory()->for($f['school'])->create(['user_id' => $learnerUser->id]);
    Sanctum::actingAs($learnerUser, ['*']);

    $this->getJson('/api/v1/me/profile')->assertOk()->assertJsonPath('data.admission_number', $student->admission_number);
});

// --- GuardianProfileController ------------------------------------------------------------------

it('queues a guardian contact-detail change for approval rather than applying it immediately', function (): void {
    $f = apiSurfaceFixture();
    $guardianUser = User::factory()->create();
    $guardianUser->schools()->attach($f['school'], ['status' => 'active']);
    $guardian = Guardian::factory()->for($f['school'])->create(['user_id' => $guardianUser->id, 'primary_phone' => '+263771111111']);
    Sanctum::actingAs($guardianUser, ['*']);

    $this->patchJson('/api/v1/me/contact-details', ['primary_phone' => '0772223333'])
        ->assertStatus(202)->assertJsonPath('data.status', 'pending');

    expect($guardian->fresh()->primary_phone)->toBe('+263771111111');
});

it('sets and reads back a guardian notification preference', function (): void {
    $f = apiSurfaceFixture();
    $guardianUser = User::factory()->create();
    $guardianUser->schools()->attach($f['school'], ['status' => 'active']);
    Guardian::factory()->for($f['school'])->create(['user_id' => $guardianUser->id]);
    Sanctum::actingAs($guardianUser, ['*']);

    $this->putJson('/api/v1/me/notification-preferences', ['preferences' => [['channel' => 'sms', 'is_enabled' => false, 'notification_key' => 'fee_reminder']]])
        ->assertOk();

    $this->getJson('/api/v1/me/notification-preferences')->assertOk()
        ->assertJsonFragment(['channel' => 'sms', 'is_enabled' => false, 'notification_key' => 'fee_reminder']);
});

// --- GuardianFinanceController additions --------------------------------------------------------

it('lists a guardian\'s own active fee liabilities', function (): void {
    $f = apiSurfaceFixture();
    $guardianUser = User::factory()->create();
    $guardianUser->schools()->attach($f['school'], ['status' => 'active']);
    $guardian = Guardian::factory()->for($f['school'])->create(['user_id' => $guardianUser->id]);
    $student = Student::factory()->for($f['school'])->create();
    FeeLiability::query()->create([
        'school_id' => $f['school']->id, 'student_id' => $student->id, 'guardian_id' => $guardian->id,
        'share_type' => 'percentage', 'share_percent' => 100, 'priority' => 100,
        'effective_from' => now()->subMonth()->toDateString(), 'is_active' => true,
    ]);
    Sanctum::actingAs($guardianUser, ['*']);

    $this->getJson('/api/v1/me/liabilities')->assertOk()
        ->assertJsonPath('data.0.admission_number', $student->admission_number)
        ->assertJsonPath('data.0.share_percent', '100.00');
});

it('refuses a statement for a learner the guardian may not view the full balance of, and returns one for a learner they may', function (): void {
    $f = apiSurfaceFixture();
    $guardianUser = User::factory()->create();
    $guardianUser->schools()->attach($f['school'], ['status' => 'active']);
    $guardian = Guardian::factory()->for($f['school'])->create(['user_id' => $guardianUser->id]);
    $restricted = Student::factory()->for($f['school'])->create();
    $visible = Student::factory()->for($f['school'])->create();
    app(LinkGuardianToStudentAction::class)->execute(new LinkGuardianToStudentData(
        studentId: $restricted->id, guardianId: $guardian->id, relationship: 'mother', createdByUserId: $f['user']->id,
        isPrimaryContact: true, isFeeResponsible: true, mayViewFullBalance: false,
    ));
    app(LinkGuardianToStudentAction::class)->execute(new LinkGuardianToStudentData(
        studentId: $visible->id, guardianId: $guardian->id, relationship: 'mother', createdByUserId: $f['user']->id,
        isPrimaryContact: true, isFeeResponsible: true, mayViewFullBalance: true,
    ));
    Sanctum::actingAs($guardianUser, ['*']);

    $this->getJson('/api/v1/me/statement?student='.$restricted->ulid.'&from=2026-01-01&to=2026-12-31')->assertStatus(404);

    $this->getJson('/api/v1/me/statement?student='.$visible->ulid.'&from=2026-01-01&to=2026-12-31&currency='.$f['school']->base_currency)
        ->assertOk()->assertJsonPath('data.lines', []);
});

// --- StaffController + StaffSelfServiceController -------------------------------------------

it('lists staff and shows one, omitting compensation fields without people.staff.view_compensation', function (): void {
    $f = apiSurfaceFixture();
    $staff = Staff::factory()->for($f['school'])->create(['bank_name' => 'CBZ', 'bank_account_number' => '0011223344']);
    Sanctum::actingAs($f['user'], ['*']);

    $this->getJson('/api/v1/staff')->assertOk()->assertJsonCount(1, 'data');

    $this->getJson("/api/v1/staff/{$staff->ulid}")->assertOk()
        ->assertJsonMissingPath('data.bank_name')
        ->assertJsonPath('data.staff_number', $staff->staff_number);
});

it('returns the signed-in staff member\'s own profile, allocations and workload', function (): void {
    $f = apiSurfaceFixture();
    $staffUser = User::factory()->create();
    $staffUser->schools()->attach($f['school'], ['status' => 'active']);
    $staff = Staff::factory()->for($f['school'])->create(['user_id' => $staffUser->id, 'is_teaching' => true]);
    $subject = Subject::factory()->for($f['school'])->create();
    $class = SchoolClass::factory()->for($f['school'])->for($f['year'])->create();
    TeacherAllocation::factory()->create([
        'school_id' => $f['school']->id, 'academic_year_id' => $f['year']->id, 'term_id' => $f['term']->id,
        'staff_id' => $staff->id, 'subject_id' => $subject->id, 'class_id' => $class->id,
    ]);
    StaffWorkload::factory()->create([
        'school_id' => $f['school']->id, 'staff_id' => $staff->id, 'term_id' => $f['term']->id,
        'teaching_periods' => 20, 'utilisation_percent' => '66.67',
    ]);
    Sanctum::actingAs($staffUser, ['*']);

    $this->getJson('/api/v1/me/staff-profile')->assertOk()->assertJsonPath('data.staff_number', $staff->staff_number);
    $this->getJson('/api/v1/me/allocations')->assertOk()->assertJsonCount(1, 'data');
    $this->getJson('/api/v1/me/workload')->assertOk()->assertJsonPath('data.computed', true)->assertJsonPath('data.teaching_periods', 20);
});

it('lists a staff member\'s own upcoming duties', function (): void {
    $f = apiSurfaceFixture();
    $staffUser = User::factory()->create();
    $staffUser->schools()->attach($f['school'], ['status' => 'active']);
    $staff = Staff::factory()->for($f['school'])->create(['user_id' => $staffUser->id]);
    $roster = DutyRoster::factory()->for($f['school'])->create();
    DutyAssignment::factory()->create(['school_id' => $f['school']->id, 'roster_id' => $roster->id, 'staff_id' => $staff->id, 'starts_at' => now()->addDay()]);
    Sanctum::actingAs($staffUser, ['*']);

    $this->getJson('/api/v1/me/duties')->assertOk()->assertJsonCount(1, 'data');
});

it('swaps a staff member\'s own duty with another, asserting consent explicitly', function (): void {
    $f = apiSurfaceFixture();
    $staffUser = User::factory()->create();
    $staffUser->schools()->attach($f['school'], ['status' => 'active']);
    $staff = Staff::factory()->for($f['school'])->create(['user_id' => $staffUser->id]);
    $other = Staff::factory()->for($f['school'])->create();
    $roster = DutyRoster::factory()->for($f['school'])->create();
    $duty = DutyAssignment::factory()->create(['school_id' => $f['school']->id, 'roster_id' => $roster->id, 'staff_id' => $staff->id]);
    Sanctum::actingAs($staffUser, ['*']);

    // The response describes the new assignment SwapDutyAssignmentAction creates for the other
    // staff member (status assigned); the caller's own original duty is the one left "swapped".
    $this->postJson("/api/v1/me/duties/{$duty->id}/swap-request", ['new_staff_id' => $other->ulid, 'both_parties_consented' => true], ['Idempotency-Key' => 'swap-1'])
        ->assertOk()->assertJsonPath('data.status', 'assigned');

    expect($duty->fresh()->status)->toBe('swapped')
        ->and(DutyAssignment::where('staff_id', $other->id)->where('status', 'assigned')->exists())->toBeTrue();
});

it('reads a staff member\'s own leave balances, requests leave, and cancels a request', function (): void {
    $f = apiSurfaceFixture();
    $staffUser = User::factory()->create();
    $staffUser->schools()->attach($f['school'], ['status' => 'active']);
    $staff = Staff::factory()->for($f['school'])->create(['user_id' => $staffUser->id]);
    $leaveType = LeaveType::factory()->for($f['school'])->create();
    LeaveBalance::factory()->create(['school_id' => $f['school']->id, 'staff_id' => $staff->id, 'leave_type_id' => $leaveType->id, 'academic_year_id' => $f['year']->id]);
    Sanctum::actingAs($staffUser, ['*']);

    $this->getJson('/api/v1/me/leave/balances')->assertOk()->assertJsonPath('data.0.available_days', '21.0');

    $created = $this->postJson('/api/v1/me/leave/requests', [
        'leave_type_id' => $leaveType->id, 'starts_on' => now()->addWeek()->toDateString(), 'ends_on' => now()->addWeek()->addDays(2)->toDateString(), 'working_days' => 3,
    ], ['Idempotency-Key' => 'leave-1'])->assertCreated();

    $ulid = $created->json('data.id');
    $this->getJson('/api/v1/me/leave/requests')->assertOk()->assertJsonCount(1, 'data');

    $this->deleteJson("/api/v1/me/leave/requests/{$ulid}")->assertOk()->assertJsonPath('data.status', 'cancelled');
});

it('refuses a leave request beyond the available balance', function (): void {
    $f = apiSurfaceFixture();
    $staffUser = User::factory()->create();
    $staffUser->schools()->attach($f['school'], ['status' => 'active']);
    $staff = Staff::factory()->for($f['school'])->create(['user_id' => $staffUser->id]);
    $leaveType = LeaveType::factory()->for($f['school'])->create();
    LeaveBalance::factory()->create(['school_id' => $f['school']->id, 'staff_id' => $staff->id, 'leave_type_id' => $leaveType->id, 'academic_year_id' => $f['year']->id, 'available_days' => 1]);
    Sanctum::actingAs($staffUser, ['*']);

    $this->postJson('/api/v1/me/leave/requests', [
        'leave_type_id' => $leaveType->id, 'starts_on' => now()->addWeek()->toDateString(), 'ends_on' => now()->addWeek()->addDays(2)->toDateString(), 'working_days' => 5,
    ], ['Idempotency-Key' => 'leave-2'])->assertStatus(422)->assertJsonPath('error.code', 'LEAVE_BALANCE_EXCEEDED');
});
