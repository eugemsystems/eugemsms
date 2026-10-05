<?php

use App\Models\User;
use Livewire\Livewire;
use Modules\Comms\Livewire\Portal\Admin\Widgets;
use Modules\Comms\Models\SchoolWidgetSetting;
use Modules\Core\Domain\Actions\Auth\UpdateUserPermissionsAction;
use Modules\Core\Domain\Actions\Settings\SetSettingValueAction;
use Modules\Core\Domain\DataObjects\Auth\PermissionGrantData;
use Modules\Core\Domain\DataObjects\Auth\UserPermissionData;
use Modules\Core\Domain\DataObjects\Settings\SetSettingValueData;
use Modules\Core\Domain\Support\Auth\PermissionScope;
use Modules\Core\Domain\Support\SchoolContext;
use Modules\Core\Domain\Support\Settings\SettingScope;
use Modules\Core\Models\Permission;
use Modules\Core\Models\School;
use Modules\Core\Models\SchoolModule;

/**
 * Book I COM-03/04/05 admin-UI pass — the one admin screen of the
 * portal services. Own, distinctly-named helpers.
 *
 * @return array<string, mixed>
 */
function portalAdminFixture(bool $walletModuleEnabled = true): array
{
    $school = School::factory()->create(['base_currency' => 'USD']);
    SchoolContext::set($school);

    SchoolModule::factory()->for($school)->create(['module_code' => 'FIN-14', 'is_enabled' => $walletModuleEnabled]);

    return ['school' => $school];
}

/**
 * @param  array<string, mixed>  $f
 */
function portalAdminUser(array $f, string ...$permissionNames): User
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

    app(UpdateUserPermissionsAction::class)->execute(new UserPermissionData(
        userId: $user->id, schoolId: $f['school']->id, grants: $grants,
    ));

    return $user;
}

it('refuses the widget screen to a user without portal.widget.view', function (): void {
    $f = portalAdminFixture();
    $outsider = User::factory()->create();
    $outsider->schools()->attach($f['school'], ['status' => 'active']);

    Livewire::actingAs($outsider)->test(Widgets::class, ['school' => $f['school']])->assertForbidden();
});

it('shows registered defaults when a school has configured nothing yet (BR-COM-03-004)', function (): void {
    $f = portalAdminFixture();
    $viewer = portalAdminUser($f, 'portal.widget.view');

    $component = Livewire::actingAs($viewer)->test(Widgets::class, ['school' => $f['school']]);

    expect($component->get('rows'))->toHaveKey('fee_balance')
        ->and($component->get('rows')['fee_balance']['enabled'])->toBeTrue();
});

it('never offers a widget whose module the school has not enabled (AC-COM-03-002, BR-COM-03-003)', function (): void {
    $f = portalAdminFixture(walletModuleEnabled: false);
    $viewer = portalAdminUser($f, 'portal.widget.view');

    $component = Livewire::actingAs($viewer)->test(Widgets::class, ['school' => $f['school']]);

    expect($component->get('rows'))->not->toHaveKey('wallet_balance_parent')
        ->and($component->get('rows'))->toHaveKey('fee_balance');
    $component->assertSee('hidden because');

    $component->set('persona', 'learner');
    expect($component->get('rows'))->not->toHaveKey('wallet_balance_learner');
});

it('offers the module’s widget once the module is enabled', function (): void {
    $f = portalAdminFixture(walletModuleEnabled: true);
    $viewer = portalAdminUser($f, 'portal.widget.view');

    expect(Livewire::actingAs($viewer)->test(Widgets::class, ['school' => $f['school']])->get('rows'))
        ->toHaveKey('wallet_balance_parent');
});

it('saves enablement and order per persona, leaving other personas untouched (BR-COM-03-004)', function (): void {
    $f = portalAdminFixture();
    $manager = portalAdminUser($f, 'portal.widget.view', 'portal.widget.manage');

    $component = Livewire::actingAs($manager)->test(Widgets::class, ['school' => $f['school']]);

    // A fresh request: the school context does not survive between Livewire calls in production.
    SchoolContext::clear();

    $component
        ->set('rows.fee_balance.enabled', false)
        ->set('rows.unread_messages_parent.sort', 1)
        ->call('save')
        ->assertHasNoErrors()
        ->assertDispatched('toast');

    $fee = SchoolWidgetSetting::where('school_id', $f['school']->id)->where('widget_key', 'fee_balance')->firstOrFail();
    $unread = SchoolWidgetSetting::where('school_id', $f['school']->id)->where('widget_key', 'unread_messages_parent')->firstOrFail();

    expect($fee->is_enabled)->toBeFalse()
        ->and($fee->persona)->toBe('parent')
        ->and($unread->sort_order)->toBe(1)
        ->and(SchoolWidgetSetting::where('school_id', $f['school']->id)->where('persona', '!=', 'parent')->count())->toBe(0);
});

it('refuses to save for a view-only user', function (): void {
    $f = portalAdminFixture();
    $viewer = portalAdminUser($f, 'portal.widget.view');

    Livewire::actingAs($viewer)->test(Widgets::class, ['school' => $f['school']])
        ->call('save')
        ->assertForbidden();

    expect(SchoolWidgetSetting::count())->toBe(0);
});

it('refuses to enable more widgets than the persona maximum, saving nothing', function (): void {
    $f = portalAdminFixture();
    $manager = portalAdminUser($f, 'portal.widget.view', 'portal.widget.manage');

    app(SetSettingValueAction::class)->execute(new SetSettingValueData(
        key: 'portal.dashboard_widget_max_per_persona',
        scopeType: SettingScope::School,
        scopeId: $f['school']->id,
        value: 1,
        setByUserId: $manager->id,
    ));

    Livewire::actingAs($manager)->test(Widgets::class, ['school' => $f['school']])
        ->call('save')
        ->assertHasErrors(['rows']);

    expect(SchoolWidgetSetting::count())->toBe(0);
});

it('tells the admin the learner “tell someone” entry point is not configurable (AC-COM-03-004)', function (): void {
    $f = portalAdminFixture();
    $viewer = portalAdminUser($f, 'portal.widget.view');

    Livewire::actingAs($viewer)->test(Widgets::class, ['school' => $f['school']])
        ->set('persona', 'learner')
        ->assertSee('tell someone')
        ->assertSee('cannot be disabled here');
});
