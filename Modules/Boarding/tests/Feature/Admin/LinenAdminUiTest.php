<?php

use App\Models\User;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Modules\Boarding\Domain\Actions\IssueItemToLearnerAction;
use Modules\Boarding\Domain\Actions\ReturnIssuedItemAction;
use Modules\Boarding\Domain\DataObjects\IssueItemToLearnerData;
use Modules\Boarding\Domain\DataObjects\ReturnIssuedItemData;
use Modules\Boarding\Livewire\Linen\Clearance;
use Modules\Boarding\Livewire\Linen\Issue;
use Modules\Boarding\Models\IssuableItem;
use Modules\Boarding\Models\LearnerIssuedItem;
use Modules\Core\Domain\Actions\Auth\UpdateUserPermissionsAction;
use Modules\Core\Domain\DataObjects\Auth\PermissionGrantData;
use Modules\Core\Domain\DataObjects\Auth\UserPermissionData;
use Modules\Core\Domain\Support\Auth\PermissionScope;
use Modules\Core\Domain\Support\SchoolContext;
use Modules\Core\Models\AcademicYear;
use Modules\Core\Models\Permission;
use Modules\Core\Models\School;
use Modules\Core\Models\Term;
use Modules\People\Models\Student;

/**
 * Book F BRD-05 admin UI — Laundry & Linen. Own, distinctly-named
 * fixture.
 *
 * @return array{school: School, year: AcademicYear, term: Term, item: IssuableItem, student: Student, user: User}
 */
function linenAdminFixture(): array
{
    $school = School::factory()->create();
    SchoolContext::set($school);
    $year = AcademicYear::factory()->for($school)->current()->create();
    $term = Term::factory()->for($school)->for($year, 'academicYear')->current()->create();
    $item = IssuableItem::factory()->create(['school_id' => $school->id, 'is_returnable' => true]);
    $student = Student::factory()->for($school)->boarder()->create();

    return ['school' => $school, 'year' => $year, 'term' => $term, 'item' => $item, 'student' => $student, 'user' => User::factory()->create()];
}

/**
 * @param  array<string, mixed>  $f
 */
function linenAdminUser(array $f, string ...$permissionNames): User
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

it('serves every BRD-05 screen through a real routed request', function (): void {
    $f = linenAdminFixture();
    $user = linenAdminUser($f, 'boarding.linen.view', 'boarding.linen.manage');

    foreach ([
        'boarding.linen.items',
        'boarding.linen.issue',
        'boarding.linen.clearance',
        'boarding.laundry.cycles',
        'boarding.laundry.missing',
    ] as $routeName) {
        $this->actingAs($user)->get(route($routeName, $f['school']))->assertOk();
    }
});

it('refuses Linen\\Issue to a user without boarding.linen.view', function (): void {
    $f = linenAdminFixture();
    $user = linenAdminUser($f);

    Livewire::actingAs($user)->test(Issue::class, ['school' => $f['school']])
        ->assertForbidden();
});

it('blocks clearance while a returnable item remains outstanding, and clears once it is returned (AC-BRD-05-001)', function (): void {
    $f = linenAdminFixture();

    app(IssueItemToLearnerAction::class)->execute(new IssueItemToLearnerData(
        schoolId: $f['school']->id,
        termId: $f['term']->id,
        studentId: $f['student']->id,
        issuableItemId: $f['item']->id,
        conditionAtIssue: 'new',
        issuedByUserId: $f['user']->id,
        issuedOn: Carbon::now(),
    ));

    $user = linenAdminUser($f, 'boarding.linen.manage');

    Livewire::actingAs($user)->test(Clearance::class, ['school' => $f['school']])
        ->set('studentId', $f['student']->id)
        ->call('check')
        ->assertSet('isClear', false);

    $issued = LearnerIssuedItem::where('student_id', $f['student']->id)->first();

    // Return it via the same Action the Issue screen itself calls —
    // isolated from the Livewire component here to avoid coupling this
    // assertion to a second component's own test lifecycle.
    app(ReturnIssuedItemAction::class)->execute(new ReturnIssuedItemData(
        learnerIssuedItemId: $issued->id,
        conditionAtReturn: 'good',
        receivedByUserId: $f['user']->id,
        returnedOn: Carbon::now(),
    ));

    Livewire::actingAs($user)->test(Clearance::class, ['school' => $f['school']])
        ->set('studentId', $f['student']->id)
        ->call('check')
        ->assertSet('isClear', true);
});
