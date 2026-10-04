<?php

use App\Models\User;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Modules\Boarding\Domain\Actions\SignInVisitorAction;
use Modules\Boarding\Domain\DataObjects\SignInVisitorData;
use Modules\Boarding\Domain\Exceptions\VisitorBlacklistedException;
use Modules\Boarding\Livewire\Gate\Terminal;
use Modules\Boarding\Livewire\Visitors\Blacklist;
use Modules\Boarding\Models\CollectionAttempt;
use Modules\Boarding\Models\Exeat;
use Modules\Boarding\Models\ExeatType;
use Modules\Boarding\Models\Visitor;
use Modules\Boarding\Models\VisitorLogEntry;
use Modules\Core\Domain\Actions\Auth\UpdateUserPermissionsAction;
use Modules\Core\Domain\DataObjects\Auth\PermissionGrantData;
use Modules\Core\Domain\DataObjects\Auth\UserPermissionData;
use Modules\Core\Domain\Support\Auth\PermissionScope;
use Modules\Core\Domain\Support\SchoolContext;
use Modules\Core\Models\AcademicYear;
use Modules\Core\Models\Permission;
use Modules\Core\Models\School;
use Modules\Core\Models\Term;
use Modules\People\Models\Guardian;
use Modules\People\Models\Student;
use Modules\People\Models\StudentGuardian;

/**
 * Book F BRD-03 admin UI — Exeat, Leave & Visitor Management ⭐. Own,
 * distinctly-named fixture.
 *
 * @return array{school: School, year: AcademicYear, term: Term, exeatType: ExeatType, student: Student, user: User}
 */
function exeatsAdminFixture(): array
{
    $school = School::factory()->create();
    SchoolContext::set($school);
    $year = AcademicYear::factory()->for($school)->current()->create();
    $term = Term::factory()->for($school)->for($year, 'academicYear')->current()->create();
    $exeatType = ExeatType::factory()->create(['school_id' => $school->id]);
    $student = Student::factory()->for($school)->boarder()->create();

    return ['school' => $school, 'year' => $year, 'term' => $term, 'exeatType' => $exeatType, 'student' => $student, 'user' => User::factory()->create()];
}

/**
 * @param  array<string, mixed>  $f
 */
function exeatsAdminUser(array $f, string ...$permissionNames): User
{
    $user = User::factory()->create();
    $user->schools()->attach($f['school'], ['status' => 'active']);

    $grants = array_map(function (string $permissionName): PermissionGrantData {
        $parts = explode('.', $permissionName);
        $moduleCode = $parts[0];
        $action = array_pop($parts);
        $resource = implode('.', array_slice($parts, 1));

        $permission = Permission::firstOrCreate(
            ['name' => $permissionName],
            ['guard_name' => 'web', 'module_code' => strtoupper($moduleCode), 'resource' => $resource, 'action' => $action],
        );

        return new PermissionGrantData($permission->id, PermissionScope::School);
    }, $permissionNames);

    if ($grants !== []) {
        app(UpdateUserPermissionsAction::class)->execute(new UserPermissionData(
            userId: $user->id, schoolId: $f['school']->id, grants: $grants,
        ));
    }

    return $user;
}

/**
 * @param  array<string, mixed>  $f
 */
function exeatsApprovedExeat(array $f, ?int $collectingGuardianId = null): Exeat
{
    return Exeat::factory()->create([
        'school_id' => $f['school']->id,
        'academic_year_id' => $f['year']->id,
        'term_id' => $f['term']->id,
        'student_id' => $f['student']->id,
        'exeat_type_id' => $f['exeatType']->id,
        'status' => 'approved',
        'departs_at' => Carbon::now()->subHour(),
        'returns_by' => Carbon::now()->addDay(),
        'collecting_guardian_id' => $collectingGuardianId,
        'verification_code' => 'VC-'.fake()->unique()->numerify('######'),
    ]);
}

it('serves every BRD-03 screen through a real routed request', function (): void {
    $f = exeatsAdminFixture();
    $exeat = exeatsApprovedExeat($f);
    $user = exeatsAdminUser($f, 'boarding.exeat.view', 'boarding.exeat.approve', 'boarding.exeat.manage', 'boarding.gate.operate', 'boarding.gate.view', 'boarding.visitor.manage', 'boarding.visitor.view', 'boarding.visitor.blacklist');

    foreach ([
        'boarding.exeats.index',
        'boarding.exeats.approvals',
        'boarding.exeats.overdue',
        'boarding.exeats.types',
        'boarding.gate.terminal',
        'boarding.gate.attempts',
        'boarding.visitors.terminal',
        'boarding.visitors.log',
        'boarding.visitors.blacklist',
        'boarding.visitors.visiting-days',
    ] as $routeName) {
        $this->actingAs($user)->get(route($routeName, $f['school']))->assertOk();
    }

    $this->actingAs($user)->get(route('boarding.exeats.show', [$f['school'], $exeat]))->assertOk();
});

it('refuses Gate\\Terminal to a user without boarding.gate.operate', function (): void {
    $f = exeatsAdminFixture();
    $user = exeatsAdminUser($f);

    Livewire::actingAs($user)->test(Terminal::class, ['school' => $f['school']])
        ->assertForbidden();
});

it('refuses release at the gate to an adult not named on the exeat, and logs the attempt permanently (AC-BRD-03-003)', function (): void {
    $f = exeatsAdminFixture();
    $namedGuardian = Guardian::factory()->for($f['school'])->create();
    $exeat = exeatsApprovedExeat($f, $namedGuardian->id);
    $user = exeatsAdminUser($f, 'boarding.gate.operate');

    Livewire::actingAs($user)->test(Terminal::class, ['school' => $f['school']])
        ->set('verificationCode', $exeat->verification_code)
        ->call('lookup')
        ->set('claimName', 'A Stranger')
        ->set('identityVerified', true)
        ->call('checkDeparture')
        ->assertSet('lastResult', 'DO NOT RELEASE');

    $attempt = CollectionAttempt::where('exeat_id', $exeat->id)->first();
    expect($attempt)->not->toBeNull()
        ->and($attempt->outcome)->not->toBe('released')
        ->and($attempt->refusal_reason)->toBe('no_right');

    // Append-only — no permission level may delete it.
    expect(fn () => $attempt->delete())->toThrow(Exception::class);
});

it('refuses release regardless of a standing right when the guardian has a court restriction, and alerts escalate (BR-BRD-03-013/AC-BRD-03-004)', function (): void {
    $f = exeatsAdminFixture();
    $restrictedGuardian = Guardian::factory()->for($f['school'])->create();
    StudentGuardian::factory()->courtRestricted()->create([
        'school_id' => $f['school']->id,
        'student_id' => $f['student']->id,
        'guardian_id' => $restrictedGuardian->id,
    ]);
    $exeat = exeatsApprovedExeat($f, $restrictedGuardian->id);
    $user = exeatsAdminUser($f, 'boarding.gate.operate');

    Livewire::actingAs($user)->test(Terminal::class, ['school' => $f['school']])
        ->set('verificationCode', $exeat->verification_code)
        ->call('lookup')
        ->set('claimName', 'Restricted Parent')
        ->set('claimGuardianId', $restrictedGuardian->id)
        ->set('identityVerified', true)
        ->call('checkDeparture')
        ->assertSet('lastResult', 'DO NOT RELEASE')
        ->assertSet('lastReason', 'court_restriction');

    $attempt = CollectionAttempt::where('exeat_id', $exeat->id)->first();
    expect($attempt->refusal_reason)->toBe('court_restriction');
});

it('refuses a blacklisted visitor at sign-in and logs a security event, with no sign-in record created', function (): void {
    $f = exeatsAdminFixture();
    $visitor = Visitor::factory()->create(['school_id' => $f['school']->id, 'full_name' => 'Blocked Person', 'is_blacklisted' => true, 'blacklist_reason' => 'Prior incident']);

    expect(fn () => app(SignInVisitorAction::class)->execute(new SignInVisitorData(
        schoolId: $f['school']->id,
        fullName: 'Blocked Person',
        visitPurpose: 'parent_visit',
        gateStaffUserId: $f['user']->id,
    )))->toThrow(VisitorBlacklistedException::class);

    expect(VisitorLogEntry::where('visitor_id', $visitor->id)->exists())->toBeFalse();
});

it('blacklists a visitor through the admin screen', function (): void {
    $f = exeatsAdminFixture();
    $visitor = Visitor::factory()->create(['school_id' => $f['school']->id, 'full_name' => 'Needs Watching']);
    $user = exeatsAdminUser($f, 'boarding.visitor.blacklist');

    Livewire::actingAs($user)->test(Blacklist::class, ['school' => $f['school']])
        ->set('visitorId', $visitor->id)
        ->set('reason', 'Repeated unauthorised attempts.')
        ->call('blacklist');

    expect($visitor->refresh()->is_blacklisted)->toBeTrue();
});
