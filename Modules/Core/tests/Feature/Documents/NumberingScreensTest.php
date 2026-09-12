<?php

use App\Models\User;
use Illuminate\Support\Facades\Route;
use Livewire\Livewire;
use Modules\Core\Domain\Actions\Auth\UpdateUserPermissionsAction;
use Modules\Core\Domain\Actions\Documents\CreateNumberingSeriesAction;
use Modules\Core\Domain\DataObjects\Auth\PermissionGrantData;
use Modules\Core\Domain\DataObjects\Auth\UserPermissionData;
use Modules\Core\Domain\DataObjects\Documents\CreateNumberingSeriesData;
use Modules\Core\Domain\Support\Auth\PermissionScope;
use Modules\Core\Livewire\Numbering\Editor;
use Modules\Core\Livewire\Numbering\GapReport;
use Modules\Core\Livewire\Numbering\Index;
use Modules\Core\Models\AllocatedNumber;
use Modules\Core\Models\NumberingSeries;
use Modules\Core\Models\Permission;
use Modules\Core\Models\School;

/**
 * See RoleScreensTest.php's identically-named-purpose helper for why
 * routes are loaded directly rather than relying on CoreServiceProvider
 * alone within an isolated test file run.
 */
function loadNumberingRoutesForTest(): void
{
    if (! Route::has('numbering.index')) {
        require base_path('Modules/Core/routes/numbering.php');
    }
}

/**
 * `core.numbering.view`/`core.numbering.manage` are enforced on these
 * screens — granted as a direct permission for the acting admin, same
 * pattern as every other screen test in this session's work.
 */
function grantNumberingPermission(User $user, School $school, string $permission): void
{
    loadNumberingRoutesForTest();

    $model = Permission::firstOrCreate(
        ['name' => $permission],
        ['guard_name' => 'web', 'module_code' => 'CORE', 'resource' => 'numbering', 'action' => last(explode('.', $permission))],
    );

    app(UpdateUserPermissionsAction::class)->execute(new UserPermissionData(
        userId: $user->id,
        schoolId: $school->id,
        grants: [new PermissionGrantData($model->id, PermissionScope::School)],
    ));
}

it('lists a school\'s numbering series', function (): void {
    $admin = User::factory()->create();
    $school = School::factory()->create();
    $admin->schools()->attach($school, ['is_primary' => true, 'status' => 'active']);
    grantNumberingPermission($admin, $school, 'core.numbering.view');

    NumberingSeries::factory()->for($school)->create(['document_type' => 'receipt']);

    Livewire::actingAs($admin)
        ->test(Index::class, ['school' => $school])
        ->assertSee('receipt');
});

it('refuses the index screen without core.numbering.view', function (): void {
    loadNumberingRoutesForTest();
    $admin = User::factory()->create();
    $school = School::factory()->create();
    $admin->schools()->attach($school, ['is_primary' => true, 'status' => 'active']);

    Livewire::actingAs($admin)
        ->test(Index::class, ['school' => $school])
        ->assertForbidden();
});

it('creates a numbering series from the editor', function (): void {
    $admin = User::factory()->create();
    $school = School::factory()->create();
    $admin->schools()->attach($school, ['is_primary' => true, 'status' => 'active']);
    grantNumberingPermission($admin, $school, 'core.numbering.manage');

    Livewire::actingAs($admin)
        ->test(Editor::class, ['school' => $school])
        ->set('documentType', 'invoice')
        ->set('pattern', '{SCHOOL}/{TYPE}/{SEQ:6}')
        ->set('resetPolicy', 'never')
        ->call('save')
        ->assertHasNoErrors();

    expect(NumberingSeries::where('school_id', $school->id)->where('document_type', 'invoice')->exists())->toBeTrue();
});

it('does not let the pattern change once numbers have been allocated in the current period (BR-CORE-06-005)', function (): void {
    $admin = User::factory()->create();
    $school = School::factory()->create();
    $admin->schools()->attach($school, ['is_primary' => true, 'status' => 'active']);
    grantNumberingPermission($admin, $school, 'core.numbering.manage');

    $series = app(CreateNumberingSeriesAction::class)->execute(new CreateNumberingSeriesData(
        schoolId: $school->id,
        documentType: 'receipt',
        pattern: '{SCHOOL}/{TYPE}/{SEQ:6}',
    ));
    $series->increment('next_sequence');

    Livewire::actingAs($admin)
        ->test(Editor::class, ['school' => $school, 'series' => $series])
        ->set('pattern', 'CHANGED/{SEQ:6}')
        ->call('save')
        ->assertDispatched('toast', variant: 'danger');

    expect($series->fresh()->pattern)->toBe('{SCHOOL}/{TYPE}/{SEQ:6}');
});

it('shows every voided number with its reason on the gap report', function (): void {
    $admin = User::factory()->create();
    $school = School::factory()->create();
    $admin->schools()->attach($school, ['is_primary' => true, 'status' => 'active']);
    grantNumberingPermission($admin, $school, 'core.numbering.view');

    $series = NumberingSeries::factory()->for($school)->create(['document_type' => 'receipt']);
    AllocatedNumber::factory()->for($school)->create([
        'series_id' => $series->id,
        'status' => 'voided',
        'void_reason' => 'Printer jam, reissued manually',
        'voided_at' => now(),
    ]);

    Livewire::actingAs($admin)
        ->test(GapReport::class, ['school' => $school])
        ->assertSee('Printer jam, reissued manually');
});
