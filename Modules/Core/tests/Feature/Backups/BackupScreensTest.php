<?php

use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Modules\Core\Domain\Actions\Auth\UpdateUserPermissionsAction;
use Modules\Core\Domain\Actions\Backups\CreateBackupAction;
use Modules\Core\Domain\DataObjects\Auth\PermissionGrantData;
use Modules\Core\Domain\DataObjects\Auth\UserPermissionData;
use Modules\Core\Domain\DataObjects\Backups\CreateBackupData;
use Modules\Core\Domain\Support\Auth\PermissionScope;
use Modules\Core\Livewire\Backups\ContractExitExport;
use Modules\Core\Livewire\Backups\Index;
use Modules\Core\Livewire\Backups\Show;
use Modules\Core\Models\Backup;
use Modules\Core\Models\File;
use Modules\Core\Models\Permission;
use Modules\Core\Models\School;

function grantBackupPermissions(User $user, School $school, string ...$permissions): void
{
    $grants = array_map(function (string $permission): PermissionGrantData {
        $model = Permission::firstOrCreate(
            ['name' => $permission],
            ['guard_name' => 'web', 'module_code' => 'CORE', 'resource' => 'backup', 'action' => last(explode('.', $permission))],
        );

        return new PermissionGrantData($model->id, PermissionScope::School);
    }, $permissions);

    app(UpdateUserPermissionsAction::class)->execute(new UserPermissionData(
        userId: $user->id,
        schoolId: $school->id,
        grants: $grants,
    ));
}

beforeEach(function (): void {
    Storage::fake('backups');
    Storage::fake('local');
});

it('lists backups and creates a new one', function (): void {
    $admin = User::factory()->create();

    Livewire::actingAs($admin)
        ->test(Index::class)
        ->set('type', 'database')
        ->call('create')
        ->assertDispatched('toast');

    expect(Backup::where('type', 'database')->where('triggered_by', 'manual')->exists())->toBeTrue();
});

it('creates a school_export backup scoped to the chosen school', function (): void {
    $admin = User::factory()->create();
    $school = School::factory()->create();

    Livewire::actingAs($admin)
        ->test(Index::class)
        ->set('type', 'school_export')
        ->set('schoolId', $school->id)
        ->call('create')
        ->assertHasNoErrors();

    $backup = Backup::where('type', 'school_export')->sole();
    expect($backup->scope)->toBe('school')
        ->and($backup->scope_id)->toBe($school->id);
});

it('runs a restore test and applies retention from the index screen', function (): void {
    $admin = User::factory()->create();
    $backup = app(CreateBackupAction::class)->execute(new CreateBackupData(type: 'database', triggeredBy: 'manual'));

    Livewire::actingAs($admin)
        ->test(Index::class)
        ->call('runRestoreTest', $backup->id)
        ->assertDispatched('toast');

    expect($backup->fresh()->status)->toBe('verified');

    Livewire::actingAs($admin)->test(Index::class)->call('runRetention')->assertDispatched('toast');
});

it('shows a backup\'s detail with its restore-test history', function (): void {
    $admin = User::factory()->create();
    $backup = app(CreateBackupAction::class)->execute(new CreateBackupData(type: 'database', triggeredBy: 'manual'));

    Livewire::actingAs($admin)
        ->test(Show::class, ['backup' => $backup])
        ->call('runRestoreTest')
        ->assertSee('Passed');
});

it('requires a different approver for a production restore request (BR-CORE-13-009)', function (): void {
    $requester = User::factory()->create();
    $approver = User::factory()->create();
    $backup = app(CreateBackupAction::class)->execute(new CreateBackupData(type: 'database', triggeredBy: 'manual'));

    Livewire::actingAs($requester)
        ->test(Show::class, ['backup' => $backup])
        ->set('reason', 'Investigating a data corruption report from the bursar.')
        ->call('requestProductionRestore')
        ->assertDispatched('toast');

    $restoreTest = $backup->restoreTests()->sole();
    expect($restoreTest->status)->toBe('pending_approval');

    // The requester themselves cannot approve their own request.
    Livewire::actingAs($requester)
        ->test(Show::class, ['backup' => $backup])
        ->call('approveProductionRestore', $restoreTest->id)
        ->assertDispatched('toast');
    expect($restoreTest->fresh()->status)->toBe('pending_approval');

    Livewire::actingAs($approver)
        ->test(Show::class, ['backup' => $backup])
        ->call('approveProductionRestore', $restoreTest->id)
        ->assertDispatched('toast');
    expect($restoreTest->fresh()->status)->toBe('approved');
});

it('generates and downloads a contract-exit export, refusing without core.backup.export', function (): void {
    $school = School::factory()->create();
    $admin = User::factory()->create();
    $admin->schools()->attach($school, ['status' => 'active']);

    Livewire::actingAs($admin)->test(ContractExitExport::class, ['school' => $school])->assertForbidden();

    grantBackupPermissions($admin, $school, 'core.backup.export');

    Livewire::actingAs($admin)
        ->test(ContractExitExport::class, ['school' => $school])
        ->call('generate')
        ->assertDispatched('toast');

    $file = File::where('school_id', $school->id)->where('category', 'contract_exit_export')->sole();

    Livewire::actingAs($admin);
    $component = new ContractExitExport;
    $component->mount($school);
    $response = $component->download($file->id);

    expect($response)->not->toBeNull();
});
