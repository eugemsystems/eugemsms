<?php

use App\Models\User;
use Modules\Core\Domain\Actions\Files\UploadFileAction;
use Modules\Core\Domain\Actions\Imports\CreateImportBatchAction;
use Modules\Core\Domain\Actions\Imports\ExecuteImportBatchAction;
use Modules\Core\Domain\Actions\Imports\GenerateImportTemplateAction;
use Modules\Core\Domain\Actions\Imports\RollbackImportBatchAction;
use Modules\Core\Domain\Actions\Imports\ValidateImportBatchAction;
use Modules\Core\Domain\DataObjects\Files\UploadFileData;
use Modules\Core\Domain\DataObjects\Imports\CreateImportBatchData;
use Modules\Core\Domain\DataObjects\Imports\ExecuteImportBatchData;
use Modules\Core\Domain\DataObjects\Imports\GenerateImportTemplateData;
use Modules\Core\Domain\DataObjects\Imports\ImportDefinitionData;
use Modules\Core\Domain\DataObjects\Imports\RollbackImportBatchData;
use Modules\Core\Domain\DataObjects\Imports\ValidateImportBatchData;
use Modules\Core\Domain\Exceptions\BatchNotValidatedException;
use Modules\Core\Domain\Exceptions\UnmetImportDependencyException;
use Modules\Core\Domain\Exceptions\UnregisteredImporterException;
use Modules\Core\Domain\Registry\ImporterRegistry;
use Modules\Core\Models\File;
use Modules\Core\Models\School;
use Modules\Core\Tests\Fixtures\TestLearnerImporter;

beforeEach(function (): void {
    ImporterRegistry::clear();
});

function registerTestLearnerImporter(School $school): void
{
    ImporterRegistry::register(new ImportDefinitionData(
        key: 'test_learner',
        label: 'Test Learners',
        moduleCode: 'CORE',
        importerClass: TestLearnerImporter::class,
        requiredPermission: 'core.import.run',
    ));

    app()->instance(TestLearnerImporter::class, new TestLearnerImporter($school->id));
}

function uploadCsv(School $school, User $user, string $csv): File
{
    return app(UploadFileAction::class)->execute(new UploadFileData(
        schoolId: $school->id,
        category: 'import_source_file',
        contents: $csv,
        originalName: 'learners.csv',
        uploadedByUserId: $user->id,
    ));
}

it('throws for an unregistered importer (BR-CORE-11-*)', function (): void {
    $school = School::factory()->create();
    $user = User::factory()->create();
    $file = uploadCsv($school, $user, "name,code\nJane,J1");

    app(CreateImportBatchAction::class)->execute(new CreateImportBatchData(
        schoolId: $school->id,
        definitionKey: 'not_registered',
        sourceFileId: $file->id,
        columnMapping: ['name' => 'name', 'code' => 'code'],
        importedByUserId: $user->id,
    ));
})->throws(UnregisteredImporterException::class);

it('blocks an import until its declared dependency has completed (BR-CORE-11-011/AC-CORE-11-005)', function (): void {
    $school = School::factory()->create();
    $user = User::factory()->create();
    registerTestLearnerImporter($school);

    ImporterRegistry::register(new ImportDefinitionData(
        key: 'test_balances',
        label: 'Test Balances',
        moduleCode: 'CORE',
        importerClass: TestLearnerImporter::class,
        requiredPermission: 'core.import.run',
        dependsOn: ['test_learner'],
    ));

    $file = uploadCsv($school, $user, "name,code\nJane,J1");

    app(CreateImportBatchAction::class)->execute(new CreateImportBatchData(
        schoolId: $school->id,
        definitionKey: 'test_balances',
        sourceFileId: $file->id,
        columnMapping: ['name' => 'name', 'code' => 'code'],
        importedByUserId: $user->id,
    ));
})->throws(UnmetImportDependencyException::class);

it('validates every row before any record is written, excluding invalid rows and producing a correction file (BR-CORE-11-001/002/004/AC-CORE-11-001)', function (): void {
    $school = School::factory()->create();
    $user = User::factory()->create();
    registerTestLearnerImporter($school);

    $file = uploadCsv($school, $user, "name,code\nJane Doe,J001\n,J002\nJohn Smith,J003");

    $batch = app(CreateImportBatchAction::class)->execute(new CreateImportBatchData(
        schoolId: $school->id,
        definitionKey: 'test_learner',
        sourceFileId: $file->id,
        columnMapping: ['name' => 'name', 'code' => 'code'],
        importedByUserId: $user->id,
    ));

    $validated = app(ValidateImportBatchAction::class)->execute(new ValidateImportBatchData($batch->id));

    expect($validated->status)->toBe('validated')
        ->and($validated->total_rows)->toBe(3)
        ->and($validated->valid_rows)->toBe(2)
        ->and($validated->invalid_rows)->toBe(1)
        ->and($validated->error_file_id)->not->toBeNull();

    $this->assertDatabaseCount('houses', 0);
});

it('refuses to execute a batch that has not been validated and approved (BR-CORE-11-003)', function (): void {
    $school = School::factory()->create();
    $user = User::factory()->create();
    registerTestLearnerImporter($school);
    $file = uploadCsv($school, $user, "name,code\nJane Doe,J001");

    $batch = app(CreateImportBatchAction::class)->execute(new CreateImportBatchData(
        schoolId: $school->id,
        definitionKey: 'test_learner',
        sourceFileId: $file->id,
        columnMapping: ['name' => 'name', 'code' => 'code'],
        importedByUserId: $user->id,
    ));

    app(ExecuteImportBatchAction::class)->execute(new ExecuteImportBatchData($batch->id, approved: true));
})->throws(BatchNotValidatedException::class);

it('imports only the valid rows once explicitly approved, recording what each row created (BR-CORE-11-005/006)', function (): void {
    $school = School::factory()->create();
    $user = User::factory()->create();
    registerTestLearnerImporter($school);
    $file = uploadCsv($school, $user, "name,code\nJane Doe,J001\n,J002");

    $batch = app(CreateImportBatchAction::class)->execute(new CreateImportBatchData(
        schoolId: $school->id,
        definitionKey: 'test_learner',
        sourceFileId: $file->id,
        columnMapping: ['name' => 'name', 'code' => 'code'],
        importedByUserId: $user->id,
    ));
    app(ValidateImportBatchAction::class)->execute(new ValidateImportBatchData($batch->id));

    $completed = app(ExecuteImportBatchAction::class)->execute(new ExecuteImportBatchData($batch->id, approved: true));

    expect($completed->status)->toBe('completed')
        ->and($completed->imported_rows)->toBe(1)
        ->and($completed->failed_rows)->toBe(0);

    $this->assertDatabaseCount('houses', 1);
    $row = $completed->rows()->where('status', 'imported')->sole();
    expect($row->created_type)->not->toBeNull()
        ->and($row->created_id)->not->toBeNull();
});

it('skips a duplicate row by default and updates it when the update strategy is chosen', function (): void {
    $school = School::factory()->create();
    $user = User::factory()->create();
    registerTestLearnerImporter($school);

    $first = uploadCsv($school, $user, "name,code\nJane Doe,J001");
    $firstBatch = app(CreateImportBatchAction::class)->execute(new CreateImportBatchData($school->id, 'test_learner', $first->id, ['name' => 'name', 'code' => 'code'], $user->id));
    app(ValidateImportBatchAction::class)->execute(new ValidateImportBatchData($firstBatch->id));
    app(ExecuteImportBatchAction::class)->execute(new ExecuteImportBatchData($firstBatch->id, approved: true));

    $second = uploadCsv($school, $user, "name,code\nJane Renamed,J001");
    $skipBatch = app(CreateImportBatchAction::class)->execute(new CreateImportBatchData($school->id, 'test_learner', $second->id, ['name' => 'name', 'code' => 'code'], $user->id, duplicateStrategy: 'skip'));
    app(ValidateImportBatchAction::class)->execute(new ValidateImportBatchData($skipBatch->id));
    $skipped = app(ExecuteImportBatchAction::class)->execute(new ExecuteImportBatchData($skipBatch->id, approved: true));

    expect($skipped->skipped_rows)->toBe(1)
        ->and($skipped->imported_rows)->toBe(0);
    $this->assertDatabaseHas('houses', ['code' => 'J001', 'name' => 'Jane Doe']);

    $third = uploadCsv($school, $user, "name,code\nJane Renamed,J001");
    $updateBatch = app(CreateImportBatchAction::class)->execute(new CreateImportBatchData($school->id, 'test_learner', $third->id, ['name' => 'name', 'code' => 'code'], $user->id, duplicateStrategy: 'update'));
    app(ValidateImportBatchAction::class)->execute(new ValidateImportBatchData($updateBatch->id));
    app(ExecuteImportBatchAction::class)->execute(new ExecuteImportBatchData($updateBatch->id, approved: true));

    $this->assertDatabaseHas('houses', ['code' => 'J001', 'name' => 'Jane Renamed']);
    $this->assertDatabaseCount('houses', 1);
});

it('rolls back exactly the records a completed batch created (BR-CORE-11-007/AC-CORE-11-004)', function (): void {
    $school = School::factory()->create();
    $user = User::factory()->create();
    registerTestLearnerImporter($school);
    $file = uploadCsv($school, $user, "name,code\nJane Doe,J001\nJohn Smith,J002");

    $batch = app(CreateImportBatchAction::class)->execute(new CreateImportBatchData($school->id, 'test_learner', $file->id, ['name' => 'name', 'code' => 'code'], $user->id));
    app(ValidateImportBatchAction::class)->execute(new ValidateImportBatchData($batch->id));
    app(ExecuteImportBatchAction::class)->execute(new ExecuteImportBatchData($batch->id, approved: true));

    $this->assertDatabaseCount('houses', 2);

    $rolledBack = app(RollbackImportBatchAction::class)->execute(new RollbackImportBatchData($batch->id));

    expect($rolledBack->status)->toBe('rolled_back');
    $this->assertDatabaseCount('houses', 0);
});

it('generates a template CSV with a header and example row from the importer', function (): void {
    $school = School::factory()->create();
    registerTestLearnerImporter($school);

    $csv = app(GenerateImportTemplateAction::class)->execute(new GenerateImportTemplateData('test_learner'));

    expect($csv)->toContain('name,code')
        ->and($csv)->toContain('Jane Doe')
        ->and($csv)->toContain('JD001');
});
