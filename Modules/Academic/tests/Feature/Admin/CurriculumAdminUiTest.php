<?php

use App\Models\User;
use Livewire\Livewire;
use Modules\Academic\Livewire\Curriculum\Frameworks;
use Modules\Academic\Livewire\Curriculum\SelectionRules;
use Modules\Academic\Models\CurriculumFramework;
use Modules\Academic\Models\Subject;
use Modules\Academic\Models\SubjectSelectionRule;
use Modules\Core\Domain\Actions\Auth\UpdateUserPermissionsAction;
use Modules\Core\Domain\DataObjects\Auth\PermissionGrantData;
use Modules\Core\Domain\DataObjects\Auth\UserPermissionData;
use Modules\Core\Domain\Support\Auth\PermissionScope;
use Modules\Core\Domain\Support\SchoolContext;
use Modules\Core\Models\Permission;
use Modules\Core\Models\School;

/**
 * Book D ACA-01 §5 admin UI — Curriculum, Learning Areas & Pathways.
 * Own, distinctly-named fixture — see `StaffAdminUiTest`'s own note on
 * why a Pest helper defined in one test file can't be relied on from
 * another run standalone.
 *
 * @return array{school: School, framework: CurriculumFramework, user: User}
 */
function curriculumAdminFixture(): array
{
    $school = School::factory()->create();
    SchoolContext::set($school);
    $framework = CurriculumFramework::factory()->for($school)->create();

    return ['school' => $school, 'framework' => $framework, 'user' => User::factory()->create()];
}

/**
 * @param  array<string, mixed>  $f
 */
function curriculumAdminUser(array $f, string ...$permissionNames): User
{
    $user = User::factory()->create();
    $user->schools()->attach($f['school'], ['status' => 'active']);

    $grants = array_map(function (string $permissionName): PermissionGrantData {
        [$moduleCode, $resource, $action] = explode('.', $permissionName);

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

it('serves Curriculum\\Frameworks through a real routed request', function (): void {
    $f = curriculumAdminFixture();
    $user = curriculumAdminUser($f, 'academic.curriculum.view');

    $this->actingAs($user)
        ->get(route('academic.curriculum.frameworks', $f['school']))
        ->assertOk();
});

it('serves every other Curriculum screen through a real routed request', function (): void {
    $f = curriculumAdminFixture();
    $user = curriculumAdminUser($f, 'academic.curriculum.view');

    foreach (['academic.curriculum.subjects', 'academic.curriculum.groups', 'academic.curriculum.offerings', 'academic.curriculum.pathways', 'academic.curriculum.selection-rules', 'academic.curriculum.prerequisites', 'academic.curriculum.syllabi'] as $routeName) {
        $this->actingAs($user)->get(route($routeName, $f['school']))->assertOk();
    }
});

it('refuses Curriculum\\Frameworks to a user without academic.curriculum.view', function (): void {
    $f = curriculumAdminFixture();
    $user = curriculumAdminUser($f);

    Livewire::actingAs($user)->test(Frameworks::class, ['school' => $f['school']])
        ->assertForbidden();
});

it('creates a curriculum framework as draft', function (): void {
    $f = curriculumAdminFixture();
    $user = curriculumAdminUser($f, 'academic.curriculum.view', 'academic.curriculum.manage');

    Livewire::actingAs($user)->test(Frameworks::class, ['school' => $f['school']])
        ->set('code', 'CAMBRIDGE_IGCSE')
        ->set('name', 'Cambridge IGCSE')
        ->set('authority', 'Cambridge International')
        ->call('create');

    expect(CurriculumFramework::where('school_id', $f['school']->id)->where('code', 'CAMBRIDGE_IGCSE')->where('status', 'draft')->exists())->toBeTrue();
});

it('blocks a subject selection over a max_total rule through the live tester (BR-ACA-01-007/008)', function (): void {
    $f = curriculumAdminFixture();
    $user = curriculumAdminUser($f, 'academic.curriculum.view', 'academic.selection_rules.manage');

    $subjects = Subject::factory()->for($f['school'])->count(9)->create(['framework_id' => $f['framework']->id]);

    $component = Livewire::actingAs($user)->test(SelectionRules::class, ['school' => $f['school']])
        ->set('frameworkId', $f['framework']->id)
        ->set('ruleType', 'max_total')
        ->set('severity', 'block')
        ->set('maxCount', '8')
        ->set('message', 'Maximum 8 subjects for Forms 1-4.')
        ->call('create');

    expect(SubjectSelectionRule::where('school_id', $f['school']->id)->where('rule_type', 'max_total')->exists())->toBeTrue();

    $component->set('testerSubjectIds', $subjects->pluck('id')->all())
        ->call('runTest');

    expect($component->get('testResult')['isValid'])->toBeFalse()
        ->and($component->get('testResult')['blocks'])->toContain('Maximum 8 subjects for Forms 1-4.');
});
