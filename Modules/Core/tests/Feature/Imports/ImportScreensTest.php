<?php

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Route;
use Livewire\Livewire;
use Modules\Core\Domain\Actions\Auth\UpdateUserPermissionsAction;
use Modules\Core\Domain\DataObjects\Auth\PermissionGrantData;
use Modules\Core\Domain\DataObjects\Auth\UserPermissionData;
use Modules\Core\Domain\DataObjects\Imports\ImportDefinitionData;
use Modules\Core\Domain\Registry\ImporterRegistry;
use Modules\Core\Domain\Support\Auth\PermissionScope;
use Modules\Core\Livewire\Imports\Batch;
use Modules\Core\Livewire\Imports\History;
use Modules\Core\Livewire\Imports\Index;
use Modules\Core\Livewire\Imports\Mapper;
use Modules\Core\Models\ImportBatch;
use Modules\Core\Models\Permission;
use Modules\Core\Models\School;
use Modules\Core\Tests\Fixtures\TestLearnerImporter;

function loadImportRoutesForTest(): void
{
    if (! Route::has('imports.index')) {
        require base_path('Modules/Core/routes/imports.php');
    }
}

function grantImportPermissions(User $user, School $school, string ...$permissions): void
{
    loadImportRoutesForTest();

    $grants = array_map(function (string $permission): PermissionGrantData {
        $model = Permission::firstOrCreate(
            ['name' => $permission],
            ['guard_name' => 'web', 'module_code' => 'CORE', 'resource' => 'import', 'action' => last(explode('.', $permission))],
        );

        return new PermissionGrantData($model->id, PermissionScope::School);
    }, $permissions);

    app(UpdateUserPermissionsAction::class)->execute(new UserPermissionData(
        userId: $user->id,
        schoolId: $school->id,
        grants: $grants,
    ));
}

function adminForImportTest(School $school): User
{
    $admin = User::factory()->create();
    $admin->schools()->attach($school, ['status' => 'active']);

    return $admin;
}

function registerTestLearnerImporterForScreens(School $school, bool $isRollbackable = true): void
{
    ImporterRegistry::register(new ImportDefinitionData(
        key: 'test_learner',
        label: 'Test Learners',
        moduleCode: 'CORE',
        importerClass: TestLearnerImporter::class,
        requiredPermission: 'core.import.run',
        isRollbackable: $isRollbackable,
    ));
    ImporterRegistry::syncToDatabase();

    app()->instance(TestLearnerImporter::class, new TestLearnerImporter($school->id));
}

beforeEach(function (): void {
    ImporterRegistry::clear();
});

it('lists registered imports with dependency status, refusing without core.import.view', function (): void {
    $school = School::factory()->create();
    $admin = adminForImportTest($school);

    Livewire::actingAs($admin)->test(Index::class, ['school' => $school])->assertForbidden();

    grantImportPermissions($admin, $school, 'core.import.view');
    registerTestLearnerImporterForScreens($school);

    Livewire::actingAs($admin)
        ->test(Index::class, ['school' => $school])
        ->assertSee('Test Learners');
});

it('downloads a template CSV from the import centre', function (): void {
    $school = School::factory()->create();
    $admin = adminForImportTest($school);
    grantImportPermissions($admin, $school, 'core.import.view');
    registerTestLearnerImporterForScreens($school);

    Livewire::actingAs($admin);
    $component = new Index;
    $component->mount($school);
    $response = $component->downloadTemplate('test_learner');

    expect($response)->not->toBeNull()
        ->and($response->getContent())->toContain('name,code');
});

it('refuses the mapper without the import\'s own required permission', function (): void {
    $school = School::factory()->create();
    $admin = adminForImportTest($school);
    registerTestLearnerImporterForScreens($school);

    Livewire::actingAs($admin)->test(Mapper::class, ['school' => $school, 'definitionKey' => 'test_learner'])->assertForbidden();
});

it('blocks the mapper with a conflict when a dependency is unmet', function (): void {
    $school = School::factory()->create();
    $admin = adminForImportTest($school);
    grantImportPermissions($admin, $school, 'core.import.run');

    ImporterRegistry::register(new ImportDefinitionData(
        key: 'test_learner',
        label: 'Test Learners',
        moduleCode: 'CORE',
        importerClass: TestLearnerImporter::class,
        requiredPermission: 'core.import.run',
    ));
    ImporterRegistry::register(new ImportDefinitionData(
        key: 'test_balances',
        label: 'Test Balances',
        moduleCode: 'CORE',
        importerClass: TestLearnerImporter::class,
        requiredPermission: 'core.import.run',
        dependsOn: ['test_learner'],
    ));
    ImporterRegistry::syncToDatabase();
    app()->instance(TestLearnerImporter::class, new TestLearnerImporter($school->id));

    Livewire::actingAs($admin)->test(Mapper::class, ['school' => $school, 'definitionKey' => 'test_balances'])->assertStatus(409);
});

it('uploads, auto-maps, and creates a batch, redirecting to the batch screen', function (): void {
    $school = School::factory()->create();
    $admin = adminForImportTest($school);
    grantImportPermissions($admin, $school, 'core.import.run');
    registerTestLearnerImporterForScreens($school);

    $csv = UploadedFile::fake()->createWithContent('learners.csv', "name,code\nJane Doe,J001\nJohn Smith,J002");

    Livewire::actingAs($admin)
        ->test(Mapper::class, ['school' => $school, 'definitionKey' => 'test_learner'])
        ->set('file', $csv)
        ->assertSet('columnMapping.0', 'name')
        ->assertSet('columnMapping.1', 'code')
        ->call('createBatch')
        ->assertRedirect();

    $batch = ImportBatch::where('school_id', $school->id)->where('definition_key', 'test_learner')->sole();
    expect($batch->status)->toBe('mapping')
        ->and($batch->column_mapping)->toBe(['name' => 'name', 'code' => 'code']);
});

it('runs validation and then approves the import from the batch screen', function (): void {
    $school = School::factory()->create();
    $admin = adminForImportTest($school);
    grantImportPermissions($admin, $school, 'core.import.run');
    registerTestLearnerImporterForScreens($school);

    $csv = UploadedFile::fake()->createWithContent('learners.csv', "name,code\nJane Doe,J001\n,J002");

    Livewire::actingAs($admin)
        ->test(Mapper::class, ['school' => $school, 'definitionKey' => 'test_learner'])
        ->set('file', $csv)
        ->call('createBatch');

    $batch = ImportBatch::where('school_id', $school->id)->sole();

    $component = Livewire::actingAs($admin)
        ->test(Batch::class, ['school' => $school, 'batch' => $batch])
        ->call('runValidation');

    expect($batch->fresh()->status)->toBe('validated')
        ->and($batch->fresh()->valid_rows)->toBe(1)
        ->and($batch->fresh()->invalid_rows)->toBe(1);

    $component->call('approveAndImport')->assertDispatched('toast');

    expect($batch->fresh()->status)->toBe('completed')
        ->and($batch->fresh()->imported_rows)->toBe(1);
    $this->assertDatabaseCount('houses', 1);
});

it('rolls back a completed batch only with core.import.rollback', function (): void {
    $school = School::factory()->create();
    $admin = adminForImportTest($school);
    grantImportPermissions($admin, $school, 'core.import.run');
    registerTestLearnerImporterForScreens($school);

    $csv = UploadedFile::fake()->createWithContent('learners.csv', "name,code\nJane Doe,J001");

    Livewire::actingAs($admin)
        ->test(Mapper::class, ['school' => $school, 'definitionKey' => 'test_learner'])
        ->set('file', $csv)
        ->call('createBatch');

    $batch = ImportBatch::where('school_id', $school->id)->sole();

    Livewire::actingAs($admin)->test(Batch::class, ['school' => $school, 'batch' => $batch])
        ->call('runValidation')
        ->call('approveAndImport');

    $this->assertDatabaseCount('houses', 1);

    Livewire::actingAs($admin)
        ->test(Batch::class, ['school' => $school, 'batch' => $batch->fresh()])
        ->call('rollback')
        ->assertForbidden();

    grantImportPermissions($admin, $school, 'core.import.run', 'core.import.rollback');

    Livewire::actingAs($admin)
        ->test(Batch::class, ['school' => $school, 'batch' => $batch->fresh()])
        ->call('rollback')
        ->assertDispatched('toast');

    $this->assertDatabaseCount('houses', 0);
    expect($batch->fresh()->status)->toBe('rolled_back');
});

it('lists batch history, refusing without core.import.view', function (): void {
    $school = School::factory()->create();
    $admin = adminForImportTest($school);
    registerTestLearnerImporterForScreens($school);

    Livewire::actingAs($admin)->test(History::class, ['school' => $school])->assertForbidden();

    grantImportPermissions($admin, $school, 'core.import.view');
    ImportBatch::factory()->create(['school_id' => $school->id, 'definition_key' => 'test_learner', 'status' => 'completed']);

    Livewire::actingAs($admin)
        ->test(History::class, ['school' => $school])
        ->assertSee('Test Learner');
});
