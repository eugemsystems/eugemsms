<?php

use App\Models\User;
use Livewire\Livewire;
use Modules\Compliance\Domain\Actions\CreateGovernanceMinuteAction;
use Modules\Compliance\Domain\Actions\RecordDataBreachAction;
use Modules\Compliance\Domain\DataObjects\CreateGovernanceMinuteData;
use Modules\Compliance\Domain\DataObjects\RecordDataBreachData;
use Modules\Compliance\Livewire\Policy\IncidentRegister\Index as IncidentRegisterIndex;
use Modules\Compliance\Livewire\Policy\Minutes\Index as MinutesIndex;
use Modules\Compliance\Livewire\Policy\Policies\Index as PoliciesIndex;
use Modules\Compliance\Livewire\Policy\StatutoryDocuments\Index as StatutoryDocumentsIndex;
use Modules\Core\Domain\Actions\Auth\UpdateUserPermissionsAction;
use Modules\Core\Domain\DataObjects\Auth\PermissionGrantData;
use Modules\Core\Domain\DataObjects\Auth\UserPermissionData;
use Modules\Core\Domain\Support\Auth\PermissionScope;
use Modules\Core\Domain\Support\SchoolContext;
use Modules\Core\Models\Permission;
use Modules\Core\Models\School;

/**
 * Book H3 CMP-04 admin-UI pass. Own, distinctly-named fixture
 * (`cmp04AdminFixture`/`cmp04AdminUser`) — `cmp04Fixture` already
 * exists in the sibling backend test file.
 *
 * @return array<string, mixed>
 */
function cmp04AdminFixture(): array
{
    $school = School::factory()->create(['base_currency' => 'USD']);
    SchoolContext::set($school);
    $user = User::factory()->create();

    return compact('school', 'user');
}

/**
 * @param  array<string, mixed>  $f
 */
function cmp04AdminUser(array $f, string ...$permissionNames): User
{
    $user = User::factory()->create();
    $user->schools()->attach($f['school'], ['status' => 'active']);

    $grants = array_map(function (string $permissionName): PermissionGrantData {
        $lastDot = strrpos($permissionName, '.');
        $moduleCode = strtoupper(substr($permissionName, 0, strpos($permissionName, '.')));
        $action = substr($permissionName, $lastDot + 1);
        $resource = substr($permissionName, strpos($permissionName, '.') + 1, $lastDot - strpos($permissionName, '.') - 1);
        $resource = $resource !== '' ? $resource : $action;

        $permission = Permission::firstOrCreate(
            ['name' => $permissionName],
            ['guard_name' => 'web', 'module_code' => $moduleCode, 'resource' => $resource, 'action' => $action],
        );

        return new PermissionGrantData($permission->id, PermissionScope::School);
    }, $permissionNames);

    app(UpdateUserPermissionsAction::class)->execute(new UserPermissionData(
        userId: $user->id, schoolId: $f['school']->id, grants: $grants,
    ));

    return $user;
}

it('refuses to mount the policies screen for a user with no policy.view grant', function (): void {
    $f = cmp04AdminFixture();
    $outsider = User::factory()->create();
    $outsider->schools()->attach($f['school'], ['status' => 'active']);

    Livewire::actingAs($outsider)->test(PoliciesIndex::class, ['school' => $f['school']])
        ->assertForbidden();
});

it('renders every policy & document register screen for a fully-permissioned user', function (): void {
    $f = cmp04AdminFixture();
    $user = cmp04AdminUser($f, 'policy.manage', 'policy.view');

    Livewire::actingAs($user)->test(PoliciesIndex::class, ['school' => $f['school']])->assertOk();
    Livewire::actingAs($user)->test(StatutoryDocumentsIndex::class, ['school' => $f['school']])->assertOk();
    Livewire::actingAs($user)->test(MinutesIndex::class, ['school' => $f['school']])->assertOk();
    Livewire::actingAs($user)->test(IncidentRegisterIndex::class, ['school' => $f['school']])->assertOk();
});

it('refuses to open a confidential minute for a user holding none of its access roles (BR-CMP-04-007)', function (): void {
    $f = cmp04AdminFixture();
    $user = cmp04AdminUser($f, 'policy.manage', 'policy.view');

    $minute = app(CreateGovernanceMinuteAction::class)->execute(new CreateGovernanceMinuteData(
        schoolId: $f['school']->id, body: 'board', meetingDate: now()->toDateString(),
        attendees: ['Board Chair'], confidentiality: 'confidential', accessRoleIds: [999999],
    ));

    Livewire::actingAs($user)->test(MinutesIndex::class, ['school' => $f['school']])
        ->call('open', $minute->id)
        ->assertDispatched('toast', variant: 'danger')
        ->assertSet('openedMinuteId', null);
});

it('excludes safeguarding from the consolidated incident register (AC-CMP-04-003)', function (): void {
    $f = cmp04AdminFixture();
    $user = cmp04AdminUser($f, 'policy.view');

    app(RecordDataBreachAction::class)->execute(new RecordDataBreachData(
        schoolId: $f['school']->id, breachType: 'loss', description: 'A USB drive was lost.',
        dataCategories: ['contact_details'], severity: 'medium', includesMinors: false,
        reportedByUserId: $f['user']->id,
    ));

    $component = Livewire::actingAs($user)->test(IncidentRegisterIndex::class, ['school' => $f['school']])
        ->set('periodStart', now()->subYear()->toDateString())
        ->set('periodEnd', now()->addDay()->toDateString())
        ->call('generate');

    $entries = $component->get('entries');

    expect($entries)->toHaveCount(1);
    expect(collect($entries)->pluck('source')->all())->toBe(['data_protection']);

    foreach ($entries as $entry) {
        expect($entry['source'])->not->toBe('safeguarding');
    }
});
