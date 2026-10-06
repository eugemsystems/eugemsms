<?php

use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Modules\Core\Domain\Actions\Backups\CreateBackupAction;
use Modules\Core\Domain\Actions\Documents\CreateDocumentTemplateAction;
use Modules\Core\Domain\Actions\Documents\GenerateDocumentAction;
use Modules\Core\Domain\Actions\Documents\RegenerateDocumentAction;
use Modules\Core\Domain\Actions\Files\RecordScanResultAction;
use Modules\Core\Domain\Actions\Files\UploadFileAction;
use Modules\Core\Domain\Contracts\Files\VirusScanner;
use Modules\Core\Domain\DataObjects\Backups\CreateBackupData;
use Modules\Core\Domain\DataObjects\Documents\CreateDocumentTemplateData;
use Modules\Core\Domain\DataObjects\Documents\GenerateDocumentData;
use Modules\Core\Domain\DataObjects\Documents\RegenerateDocumentData;
use Modules\Core\Domain\DataObjects\Files\ScanResult;
use Modules\Core\Domain\DataObjects\Files\UploadFileData;
use Modules\Core\Domain\Registry\TemplateVariableRegistry;
use Modules\Core\Jobs\ScanFileJob;
use Modules\Core\Models\School;

beforeEach(function (): void {
    config([
        'filesystems.documents_disk' => 'docs-test',
        'filesystems.backups_disk' => 'backups-test',
    ]);
    Storage::fake('docs-test');
    Storage::fake('backups-test');
    Storage::fake('local');
    Storage::fake('backups');
});

it('uploads to the configured documents disk and scans it from there', function (): void {
    $school = School::factory()->create();
    $user = User::factory()->create();

    $file = app(UploadFileAction::class)->execute(new UploadFileData(
        schoolId: $school->id,
        category: 'import_source_file',
        contents: 'fake-file-bytes',
        originalName: 'a.csv',
        uploadedByUserId: $user->id,
    ));

    expect($file->disk)->toBe('docs-test');
    Storage::disk('docs-test')->assertExists($file->path);
    Storage::disk('local')->assertMissing($file->path);

    $seen = new ArrayObject;
    app()->instance(VirusScanner::class, new class($seen) implements VirusScanner
    {
        public function __construct(private ArrayObject $seen) {}

        public function scan(string $filePath): ScanResult
        {
            $this->seen['contents'] = file_get_contents($filePath);

            return new ScanResult('clean');
        }
    });

    (new ScanFileJob($file->id))->handle(app(VirusScanner::class), app(RecordScanResultAction::class));

    expect($seen['contents'])->toBe('fake-file-bytes');
});

it('generates and regenerates documents on the configured disk', function (): void {
    TemplateVariableRegistry::clear();
    TemplateVariableRegistry::register('receipt', ['school.name']);
    $school = School::factory()->create();
    $user = User::factory()->create();
    app(CreateDocumentTemplateAction::class)->execute(new CreateDocumentTemplateData(
        schoolId: $school->id,
        templateType: 'receipt',
        name: 'Standard receipt',
        content: '<p>{{ school.name }}</p>',
        isDefault: true,
    ));

    $document = app(GenerateDocumentAction::class)->execute(new GenerateDocumentData(
        schoolId: $school->id,
        documentType: 'receipt',
        data: ['school' => ['name' => $school->name]],
        generatedByUserId: $user->id,
    ));

    Storage::disk('docs-test')->assertExists($document->file_path);
    Storage::disk('local')->assertMissing($document->file_path);

    $again = app(RegenerateDocumentAction::class)->execute(new RegenerateDocumentData($document->id, ['school' => ['name' => $school->name]]));

    expect($again->file_hash)->toBe($document->file_hash);
    Storage::disk('docs-test')->assertExists($again->file_path);
    expect(Storage::disk('docs-test')->download($document->file_path)->getStatusCode())->toBe(200);
});

it('writes backups to the configured backups disk', function (): void {
    $backup = app(CreateBackupAction::class)->execute(new CreateBackupData(type: 'database', triggeredBy: 'manual'));

    expect($backup->disk)->toBe('backups-test');
    Storage::disk('backups-test')->assertExists($backup->path);
    Storage::disk('backups')->assertMissing($backup->path);
});
