<?php

use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Livewire\Livewire;
use Modules\Core\Domain\Actions\Auth\UpdateUserPermissionsAction;
use Modules\Core\Domain\DataObjects\Auth\PermissionGrantData;
use Modules\Core\Domain\DataObjects\Auth\UserPermissionData;
use Modules\Core\Domain\Support\Auth\PermissionScope;
use Modules\Core\Domain\Support\SchoolContext;
use Modules\Core\Models\AcademicYear;
use Modules\Core\Models\GradeLevel;
use Modules\Core\Models\Permission;
use Modules\Core\Models\School;
use Modules\Core\Models\Term;
use Modules\Intelligence\Domain\Actions\ReviewWithdrawalRiskFlagAction;
use Modules\Intelligence\Domain\Actions\SetRiskScoreWeightAction;
use Modules\Intelligence\Livewire\EarlyWarning\Enrolment;
use Modules\Intelligence\Livewire\EarlyWarning\FeeRisk;
use Modules\Intelligence\Livewire\EarlyWarning\Queue;
use Modules\Intelligence\Livewire\EarlyWarning\StaffWellbeing;
use Modules\Intelligence\Livewire\EarlyWarning\StudentDetail;
use Modules\Intelligence\Livewire\EarlyWarning\Weights;
use Modules\Intelligence\Models\EnrolmentForecast;
use Modules\Intelligence\Models\FeeDefaultRiskScore;
use Modules\Intelligence\Models\LearnerRiskScore;
use Modules\Intelligence\Models\RiskScoreWeight;
use Modules\Intelligence\Models\StaffWellbeingIndicator;
use Modules\Intelligence\Models\WithdrawalRiskFlag;
use Modules\People\Models\Guardian;
use Modules\People\Models\Staff;
use Modules\People\Models\Student;

/**
 * Book J INT-03 admin-UI pass. Own, distinctly-named helpers.
 *
 * @return array<string, mixed>
 */
function int03AdminFixture(): array
{
    $school = School::factory()->create(['base_currency' => 'USD']);
    SchoolContext::set($school);
    $year = AcademicYear::factory()->for($school)->create(['is_current' => true]);
    $term = Term::factory()->for($school)->for($year, 'academicYear')->create(['number' => 1, 'starts_on' => now()->subMonth(), 'ends_on' => now()->addMonth()]);

    return compact('school', 'year', 'term');
}

/**
 * @param  array<string, mixed>  $f
 */
function int03AdminUser(array $f, string ...$permissionNames): User
{
    $user = User::factory()->create();
    $user->schools()->attach($f['school'], ['status' => 'active']);

    $grants = array_map(function (string $permissionName): PermissionGrantData {
        $parts = explode('.', $permissionName);
        $action = end($parts);

        $permission = Permission::firstOrCreate(
            ['name' => $permissionName],
            ['guard_name' => 'web', 'module_code' => strtoupper($parts[0]), 'resource' => count($parts) > 2 ? $parts[1] : $action, 'action' => $action],
        );

        return new PermissionGrantData($permission->id, PermissionScope::School);
    }, $permissionNames);

    app(UpdateUserPermissionsAction::class)->execute(new UserPermissionData(
        userId: $user->id, schoolId: $f['school']->id, grants: $grants,
    ));

    return $user;
}

it('refuses every INT-03 screen to a user without its permission (AC-INT-03-002)', function (string $component, array $extra): void {
    $f = int03AdminFixture();
    $outsider = User::factory()->create();
    $outsider->schools()->attach($f['school'], ['status' => 'active']);

    $params = ['school' => $f['school']] + (isset($extra['student']) ? ['student' => Student::factory()->for($f['school'])->create()->id] : []);

    Livewire::actingAs($outsider)->test($component, $params)->assertForbidden();
})->with([
    'queue' => [Queue::class, []],
    'detail' => [StudentDetail::class, ['student' => true]],
    'fee risk' => [FeeRisk::class, []],
    'enrolment' => [Enrolment::class, []],
    'wellbeing' => [StaffWellbeing::class, []],
    'weights' => [Weights::class, []],
]);

it('renders every INT-03 screen for a permitted user', function (): void {
    $f = int03AdminFixture();
    $user = int03AdminUser($f, 'risk.review', 'risk.configure', 'executive.dashboard.view', 'finance.report.debtors', 'staff.wellbeing.view');
    $student = Student::factory()->for($f['school'])->create();

    foreach ([Queue::class, FeeRisk::class, Enrolment::class, StaffWellbeing::class, Weights::class] as $component) {
        Livewire::actingAs($user)->test($component, ['school' => $f['school']])->assertOk();
    }

    Livewire::actingAs($user)->test(StudentDetail::class, ['school' => $f['school'], 'student' => $student->id])->assertOk();
});

it('lists high and critical learners by default and hides low ones', function (): void {
    $f = int03AdminFixture();
    $user = int03AdminUser($f, 'risk.review');
    $risky = Student::factory()->for($f['school'])->create(['first_name' => 'Riskyname']);
    $calm = Student::factory()->for($f['school'])->create(['first_name' => 'Calmname']);

    LearnerRiskScore::factory()->for($f['school'])->create(['student_id' => $risky->id, 'term_id' => $f['term']->id, 'composite_score' => 80, 'risk_band' => 'critical']);
    LearnerRiskScore::factory()->for($f['school'])->create(['student_id' => $calm->id, 'term_id' => $f['term']->id, 'composite_score' => 5, 'risk_band' => 'low']);

    Livewire::actingAs($user)->test(Queue::class, ['school' => $f['school']])
        ->assertSee('Riskyname')->assertDontSee('Calmname')
        ->set('bands', ['low'])->assertSee('Calmname')->assertDontSee('Riskyname');
});

it('refuses to close a withdrawal flag without an intervention note, then closes with one (AC-INT-03-004)', function (): void {
    $f = int03AdminFixture();
    $user = int03AdminUser($f, 'risk.review');
    $student = Student::factory()->for($f['school'])->create();
    $flag = WithdrawalRiskFlag::factory()->for($f['school'])->create(['student_id' => $student->id, 'status' => 'open']);

    $component = Livewire::actingAs($user)->test(Queue::class, ['school' => $f['school']])
        ->call('startReview', $flag->id)
        ->set('interventionNote', '   ')
        ->call('closeFlag')
        ->assertHasErrors('interventionNote');

    expect($flag->fresh()->status)->toBe('open');

    $component->set('interventionNote', 'Met the family; payment plan agreed.')->call('closeFlag')->assertHasNoErrors();

    expect($flag->fresh())->status->toBe('intervention_logged')->intervention_note->toBe('Met the family; payment plan agreed.')->reviewed_by->toBe($user->id);
});

it('cannot review another school’s flag, or one that is already closed', function (): void {
    $f = int03AdminFixture();
    $user = int03AdminUser($f, 'risk.review');
    $otherSchool = School::factory()->create();
    $foreign = WithdrawalRiskFlag::factory()->for($otherSchool)->create(['status' => 'open']);
    SchoolContext::set($f['school']);
    $closed = WithdrawalRiskFlag::factory()->for($f['school'])->create(['status' => 'resolved']);

    $component = Livewire::actingAs($user)->test(Queue::class, ['school' => $f['school']]);

    expect(fn () => $component->call('startReview', $foreign->id))->toThrow(ModelNotFoundException::class);
    expect(fn () => $component->call('startReview', $closed->id))->toThrow(ModelNotFoundException::class);
    expect(fn () => app(ReviewWithdrawalRiskFlagAction::class)->execute($closed->id, 'resolved', $user->id, 'again'))->toThrow(InvalidArgumentException::class);
    expect(fn () => app(ReviewWithdrawalRiskFlagAction::class)->execute($closed->id, 'bogus', $user->id, 'x'))->toThrow(InvalidArgumentException::class);
});

it('shows every factor with its weight, contribution and source on the detail screen (AC-INT-03-001)', function (): void {
    $f = int03AdminFixture();
    $user = int03AdminUser($f, 'risk.review');
    $student = Student::factory()->for($f['school'])->create();

    LearnerRiskScore::factory()->for($f['school'])->create([
        'student_id' => $student->id, 'term_id' => $f['term']->id, 'composite_score' => 55, 'risk_band' => 'high',
        'contributing_factors' => [['indicator' => 'attendance_decline', 'plain_language' => 'Attendance fell from 94% to 71%', 'weight' => 30, 'contribution' => 21.5, 'source' => 'ACA-04 attendance_summaries']],
    ]);

    Livewire::actingAs($user)->test(StudentDetail::class, ['school' => $f['school'], 'student' => $student->id])
        ->assertSee('Attendance fell from 94% to 71%')->assertSee('ACA-04 attendance_summaries')->assertSee('21.5');
});

it('does not resolve a learner from another school on the detail screen', function (): void {
    $f = int03AdminFixture();
    $user = int03AdminUser($f, 'risk.review');
    $other = School::factory()->create();
    $foreign = Student::factory()->for($other)->create();
    SchoolContext::set($f['school']);

    expect(fn () => Livewire::actingAs($user)->test(StudentDetail::class, ['school' => $f['school'], 'student' => $foreign->id]))->toThrow(ModelNotFoundException::class);
});

it('keeps weights within 0–100, registered indicators only, and lets one be disabled (BR-INT-03-003)', function (): void {
    $f = int03AdminFixture();
    $user = int03AdminUser($f, 'risk.configure');

    $component = Livewire::actingAs($user)->test(Weights::class, ['school' => $f['school']])
        ->set('rows.attendance_decline.weight', '150')->call('save', 'attendance_decline')
        ->assertHasErrors('rows.attendance_decline.weight');

    expect(RiskScoreWeight::count())->toBe(0);

    $component->set('rows.attendance_decline.weight', '40')->set('rows.attendance_decline.enabled', false)->call('save', 'attendance_decline')->assertHasNoErrors();

    expect(RiskScoreWeight::first())->weight->toBe(40.0)->is_enabled->toBeFalse();

    expect(fn () => app(SetRiskScoreWeightAction::class)->execute($f['school']->id, 'invented_signal', 10, true))->toThrow(InvalidArgumentException::class);
});

it('shows a staff member only their own and their direct reports’ wellbeing (BR-INT-03-010)', function (): void {
    $f = int03AdminFixture();
    $manager = int03AdminUser($f, 'staff.wellbeing.view');
    $managerStaff = Staff::factory()->for($f['school'])->create(['user_id' => $manager->id, 'first_name' => 'Manny', 'last_name' => 'Manager']);
    $report = Staff::factory()->for($f['school'])->create(['reports_to_staff_id' => $managerStaff->id, 'first_name' => 'Reggie', 'last_name' => 'Report']);
    $peer = Staff::factory()->for($f['school'])->create(['first_name' => 'Peter', 'last_name' => 'Peer']);

    foreach ([$managerStaff, $report, $peer] as $member) {
        StaffWellbeingIndicator::factory()->for($f['school'])->create(['staff_id' => $member->id, 'term_id' => $f['term']->id, 'flag_level' => 'watch']);
    }

    Livewire::actingAs($manager)->test(StaffWellbeing::class, ['school' => $f['school']])
        ->assertSee('Manny')->assertSee('Reggie')->assertDontSee('Peter')
        ->call('recalculate', $peer->id)->assertForbidden();

    // The report sees only themselves — not the manager above them.
    $reportUser = int03AdminUser($f, 'staff.wellbeing.view');
    $report->update(['user_id' => $reportUser->id]);

    Livewire::actingAs($reportUser)->test(StaffWellbeing::class, ['school' => $f['school']])
        ->assertSee('Reggie')->assertDontSee('Manny')->assertDontSee('Peter');
});

it('shows nothing to a permitted user who is neither staff nor a line manager', function (): void {
    $f = int03AdminFixture();
    $admin = int03AdminUser($f, 'staff.wellbeing.view');
    $someone = Staff::factory()->for($f['school'])->create(['first_name' => 'Hidden', 'last_name' => 'Person']);
    StaffWellbeingIndicator::factory()->for($f['school'])->create(['staff_id' => $someone->id, 'term_id' => $f['term']->id]);

    Livewire::actingAs($admin)->test(StaffWellbeing::class, ['school' => $f['school']])->assertDontSee('Hidden');
});

it('shows fee risk only to finance report holders and recomputes only for risk reviewers', function (): void {
    $f = int03AdminFixture();
    $viewer = int03AdminUser($f, 'finance.report.debtors');
    $student = Student::factory()->for($f['school'])->create();
    $guardian = Guardian::factory()->for($f['school'])->create(['first_name' => 'Gina', 'last_name' => 'Guardian']);
    FeeDefaultRiskScore::factory()->for($f['school'])->create(['student_id' => $student->id, 'guardian_id' => $guardian->id, 'risk_score' => 60, 'recommended_action' => 'offer_payment_plan']);

    Livewire::actingAs($viewer)->test(FeeRisk::class, ['school' => $f['school']])
        ->assertSee('Gina Guardian')->assertSee('Offer a payment plan')
        ->call('recompute')->assertForbidden();

    // Nothing is outstanding, so a reviewer's recompute clears the stale row.
    $reviewer = int03AdminUser($f, 'finance.report.collections', 'risk.review');
    Livewire::actingAs($reviewer)->test(FeeRisk::class, ['school' => $f['school']])->call('recompute');

    expect(FeeDefaultRiskScore::count())->toBe(0);
});

it('marks a forecast on thin history low-confidence rather than hiding it (AC-INT-03-006)', function (): void {
    $f = int03AdminFixture();
    $user = int03AdminUser($f, 'executive.dashboard.view', 'risk.configure');
    GradeLevel::factory()->for($f['school'])->create();

    Livewire::actingAs($user)->test(Enrolment::class, ['school' => $f['school']])
        ->call('generate')->assertSee('Low');

    expect(EnrolmentForecast::where('confidence_band', 'low')->count())->toBeGreaterThan(0);

    $viewerOnly = int03AdminUser($f, 'executive.dashboard.view');
    Livewire::actingAs($viewerOnly)->test(Enrolment::class, ['school' => $f['school']])->call('generate')->assertForbidden();
});
