<?php

use App\Models\User;
use Illuminate\Support\Facades\Route;
use Livewire\Livewire;
use Modules\Core\Domain\Actions\Auth\UpdateUserPermissionsAction;
use Modules\Core\Domain\Actions\Documents\CreateDocumentTemplateAction;
use Modules\Core\Domain\DataObjects\Auth\PermissionGrantData;
use Modules\Core\Domain\DataObjects\Auth\UserPermissionData;
use Modules\Core\Domain\DataObjects\Documents\CreateDocumentTemplateData;
use Modules\Core\Domain\Registry\TemplateVariableRegistry;
use Modules\Core\Domain\Support\Auth\PermissionScope;
use Modules\Core\Livewire\Templates\Editor;
use Modules\Core\Livewire\Templates\Index;
use Modules\Core\Livewire\Templates\Versions;
use Modules\Core\Models\DocumentTemplate;
use Modules\Core\Models\Permission;
use Modules\Core\Models\School;

/**
 * See RoleScreensTest.php's identically-named-purpose helper for why
 * routes are loaded directly rather than relying on CoreServiceProvider
 * alone within an isolated test file run.
 */
function loadTemplateRoutesForTest(): void
{
    if (! Route::has('templates.index')) {
        require base_path('Modules/Core/routes/templates.php');
    }
}

function grantTemplatePermission(User $user, School $school, string $permission): void
{
    loadTemplateRoutesForTest();

    $model = Permission::firstOrCreate(
        ['name' => $permission],
        ['guard_name' => 'web', 'module_code' => 'CORE', 'resource' => 'template', 'action' => last(explode('.', $permission))],
    );

    app(UpdateUserPermissionsAction::class)->execute(new UserPermissionData(
        userId: $user->id,
        schoolId: $school->id,
        grants: [new PermissionGrantData($model->id, PermissionScope::School)],
    ));
}

beforeEach(function (): void {
    TemplateVariableRegistry::clear();
});

it('lists only the active version of each template', function (): void {
    $admin = User::factory()->create();
    $school = School::factory()->create();
    $admin->schools()->attach($school, ['is_primary' => true, 'status' => 'active']);
    grantTemplatePermission($admin, $school, 'core.template.view');

    DocumentTemplate::factory()->for($school)->create(['name' => 'Current Receipt', 'is_active' => true]);
    DocumentTemplate::factory()->for($school)->create(['name' => 'Old Receipt', 'is_active' => false]);

    Livewire::actingAs($admin)
        ->test(Index::class, ['school' => $school])
        ->assertSee('Current Receipt')
        ->assertDontSee('Old Receipt');
});

it('creates a template with only static content, no registered variables needed', function (): void {
    $admin = User::factory()->create();
    $school = School::factory()->create();
    $admin->schools()->attach($school, ['is_primary' => true, 'status' => 'active']);
    grantTemplatePermission($admin, $school, 'core.template.create');

    Livewire::actingAs($admin)
        ->test(Editor::class, ['school' => $school])
        ->set('templateType', 'notice')
        ->set('name', 'Simple Notice')
        ->set('content', '<p>Static text only.</p>')
        ->call('save')
        ->assertHasNoErrors();

    expect(DocumentTemplate::where('school_id', $school->id)->where('template_type', 'notice')->exists())->toBeTrue();
});

it('toasts a validation error when the content references an unregistered variable', function (): void {
    $admin = User::factory()->create();
    $school = School::factory()->create();
    $admin->schools()->attach($school, ['is_primary' => true, 'status' => 'active']);
    grantTemplatePermission($admin, $school, 'core.template.create');

    Livewire::actingAs($admin)
        ->test(Editor::class, ['school' => $school])
        ->set('templateType', 'notice')
        ->set('name', 'Simple Notice')
        ->set('content', '<p>{{ nonexistent.thing }}</p>')
        ->call('save')
        ->assertDispatched('toast', variant: 'danger');

    expect(DocumentTemplate::where('school_id', $school->id)->exists())->toBeFalse();
});

it('shows the registered variable palette for a known template type', function (): void {
    $admin = User::factory()->create();
    $school = School::factory()->create();
    $admin->schools()->attach($school, ['is_primary' => true, 'status' => 'active']);
    grantTemplatePermission($admin, $school, 'core.template.create');

    TemplateVariableRegistry::register('receipt', ['school.name', 'invoice.balance_minor']);

    Livewire::actingAs($admin)
        ->test(Editor::class, ['school' => $school])
        ->set('templateType', 'receipt')
        ->assertSee('school.name')
        ->assertSee('invoice.balance_minor');
});

it('creates a new version rather than mutating the row when editing', function (): void {
    $admin = User::factory()->create();
    $school = School::factory()->create();
    $admin->schools()->attach($school, ['is_primary' => true, 'status' => 'active']);
    grantTemplatePermission($admin, $school, 'core.template.update');

    $template = app(CreateDocumentTemplateAction::class)->execute(new CreateDocumentTemplateData(
        schoolId: $school->id,
        templateType: 'notice',
        name: 'Original',
        content: '<p>v1</p>',
    ));

    Livewire::actingAs($admin)
        ->test(Editor::class, ['school' => $school, 'template' => $template])
        ->set('content', '<p>v2</p>')
        ->call('save')
        ->assertHasNoErrors();

    expect($template->fresh()->is_active)->toBeFalse()
        ->and(DocumentTemplate::where('school_id', $school->id)->where('template_type', 'notice')->where('is_active', true)->sole()->content)->toBe('<p>v2</p>');
});

it('lists every version of a template type on the version history screen', function (): void {
    $admin = User::factory()->create();
    $school = School::factory()->create();
    $admin->schools()->attach($school, ['is_primary' => true, 'status' => 'active']);
    grantTemplatePermission($admin, $school, 'core.template.view');

    $v1 = app(CreateDocumentTemplateAction::class)->execute(new CreateDocumentTemplateData(
        schoolId: $school->id,
        templateType: 'notice',
        name: 'Original',
        content: '<p>v1</p>',
    ));

    Livewire::actingAs($admin)
        ->test(Versions::class, ['school' => $school, 'templateType' => 'notice'])
        ->assertSee('v1')
        ->set('compareLeftId', $v1->id)
        ->set('compareRightId', $v1->id)
        ->assertSee('<p>v1</p>');
});
