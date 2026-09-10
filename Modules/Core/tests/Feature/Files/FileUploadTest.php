<?php

use App\Models\User;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Modules\Core\Domain\Actions\Files\DeleteFileAction;
use Modules\Core\Domain\Actions\Files\GenerateSignedFileUrlAction;
use Modules\Core\Domain\Actions\Files\RecordScanResultAction;
use Modules\Core\Domain\Actions\Files\SetStorageQuotaAction;
use Modules\Core\Domain\Actions\Files\UploadFileAction;
use Modules\Core\Domain\DataObjects\Files\GenerateSignedFileUrlData;
use Modules\Core\Domain\DataObjects\Files\RecordScanResultData;
use Modules\Core\Domain\DataObjects\Files\ScanResult;
use Modules\Core\Domain\DataObjects\Files\SetStorageQuotaData;
use Modules\Core\Domain\DataObjects\Files\UploadFileData;
use Modules\Core\Domain\Exceptions\FileAccessDeniedException;
use Modules\Core\Domain\Exceptions\InvalidFileContentException;
use Modules\Core\Domain\Exceptions\MissingExpiryDateException;
use Modules\Core\Domain\Exceptions\StorageQuotaExceededException;
use Modules\Core\Domain\Exceptions\UnregisteredFileCategoryException;
use Modules\Core\Jobs\ScanFileJob;
use Modules\Core\Models\School;

beforeEach(function (): void {
    Storage::fake('local');
});

function tinyPng(): string
{
    return base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=');
}

it('rejects an upload to an unregistered category (BR-CORE-10-001)', function (): void {
    $school = School::factory()->create();
    $user = User::factory()->create();

    app(UploadFileAction::class)->execute(new UploadFileData(
        schoolId: $school->id,
        category: 'not_a_real_category',
        contents: 'hello',
        originalName: 'note.txt',
        uploadedByUserId: $user->id,
    ));
})->throws(UnregisteredFileCategoryException::class);

it('rejects a file renamed to look like an image via content inspection (BR-CORE-10-002/AC-CORE-10-001)', function (): void {
    $school = School::factory()->create();
    $user = User::factory()->create();

    app(UploadFileAction::class)->execute(new UploadFileData(
        schoolId: $school->id,
        category: 'learner_photo',
        contents: "<?php echo 'not actually a jpeg'; ?>",
        originalName: 'photo.jpg',
        uploadedByUserId: $user->id,
    ));
})->throws(InvalidFileContentException::class);

it('rejects an oversized file', function (): void {
    $school = School::factory()->create();
    $user = User::factory()->create();

    app(UploadFileAction::class)->execute(new UploadFileData(
        schoolId: $school->id,
        category: 'signature',
        contents: str_repeat(tinyPng(), 100000),
        originalName: 'sig.png',
        uploadedByUserId: $user->id,
    ));
})->throws(InvalidFileContentException::class);

it('rejects an upload to a category requiring expiry with none given (BR-CORE-10-009)', function (): void {
    $school = School::factory()->create();
    $user = User::factory()->create();

    app(UploadFileAction::class)->execute(new UploadFileData(
        schoolId: $school->id,
        category: 'learner_permit',
        contents: tinyPng(),
        originalName: 'permit.png',
        uploadedByUserId: $user->id,
    ));
})->throws(MissingExpiryDateException::class);

it('stores a valid upload and namespaces the path by school and category (BR-CORE-10-004)', function (): void {
    $school = School::factory()->create();
    $user = User::factory()->create();

    $file = app(UploadFileAction::class)->execute(new UploadFileData(
        schoolId: $school->id,
        category: 'signature',
        contents: tinyPng(),
        originalName: 'sig.png',
        uploadedByUserId: $user->id,
    ));

    expect($file->path)->toStartWith("school/{$school->id}/signature/")
        ->and($file->fresh()->scan_status)->toBe('skipped')
        ->and(Storage::disk('local')->exists($file->path))->toBeTrue();
});

it('deduplicates identical content within a school, storing it once (BR-CORE-10-008)', function (): void {
    $school = School::factory()->create();
    $user = User::factory()->create();
    $contents = tinyPng();

    $first = app(UploadFileAction::class)->execute(new UploadFileData($school->id, 'signature', $contents, 'a.png', $user->id));
    $second = app(UploadFileAction::class)->execute(new UploadFileData($school->id, 'signature', $contents, 'b.png', $user->id));

    expect($second->path)->toBe($first->path)
        ->and($second->id)->not->toBe($first->id);
});

it('rejects a new upload once the storage quota is exhausted, while existing files remain accessible (BR-CORE-10-010/AC-CORE-10-005)', function (): void {
    $school = School::factory()->create();
    $user = User::factory()->create();
    $existing = app(UploadFileAction::class)->execute(new UploadFileData($school->id, 'signature', tinyPng(), 'a.png', $user->id));

    app(SetStorageQuotaAction::class)->execute(new SetStorageQuotaData($school->id, quotaBytes: 1));

    expect(fn () => app(UploadFileAction::class)->execute(new UploadFileData($school->id, 'signature', tinyPng().tinyPng(), 'b.png', $user->id)))
        ->toThrow(StorageQuotaExceededException::class);

    $result = app(GenerateSignedFileUrlAction::class)->execute(new GenerateSignedFileUrlData($existing->id, $user->id));
    expect($result->url)->toContain($existing->ulid);
});

it('denies access to a scan-pending file for anyone but its uploader (BR-CORE-10-005/AC-CORE-10-002)', function (): void {
    Queue::fake();
    $school = School::factory()->create();
    $uploader = User::factory()->create();
    $stranger = User::factory()->create();

    $file = app(UploadFileAction::class)->execute(new UploadFileData($school->id, 'signature', tinyPng(), 'a.png', $uploader->id));

    Queue::assertPushed(ScanFileJob::class);
    expect($file->scan_status)->toBe('pending');

    app(GenerateSignedFileUrlAction::class)->execute(new GenerateSignedFileUrlData($file->id, $uploader->id));

    expect(fn () => app(GenerateSignedFileUrlAction::class)->execute(new GenerateSignedFileUrlData($file->id, $stranger->id)))
        ->toThrow(FileAccessDeniedException::class);
});

it('quarantines an infected file and raises a critical security event (BR-CORE-10-005)', function (): void {
    $school = School::factory()->create();
    $user = User::factory()->create();
    Queue::fake();
    $file = app(UploadFileAction::class)->execute(new UploadFileData($school->id, 'signature', tinyPng(), 'a.png', $user->id));

    app(RecordScanResultAction::class)->execute(new RecordScanResultData($file->id, new ScanResult('infected', 'EICAR test signature')));

    $this->assertDatabaseHas('security_events', ['event_type' => 'infected_file_upload', 'severity' => 'critical']);

    expect(fn () => app(GenerateSignedFileUrlAction::class)->execute(new GenerateSignedFileUrlData($file->id, $user->id)))
        ->toThrow(FileAccessDeniedException::class);
});

it('generates thumbnail, medium, and large WebP variants for an image category (BR-CORE-10-006/AC-CORE-10-003)', function (): void {
    $school = School::factory()->create();
    $user = User::factory()->create();

    $file = app(UploadFileAction::class)->execute(new UploadFileData($school->id, 'learner_photo', tinyPng(), 'photo.png', $user->id));

    expect($file->fresh()->variants)->toHaveKeys(['thumb', 'medium', 'large']);
});

it('falls back to the original when the requested variant does not exist', function (): void {
    $school = School::factory()->create();
    $user = User::factory()->create();
    Queue::fake();
    $file = app(UploadFileAction::class)->execute(new UploadFileData($school->id, 'signature', tinyPng(), 'a.png', $user->id));

    $result = app(GenerateSignedFileUrlAction::class)->execute(new GenerateSignedFileUrlData($file->id, $user->id, variant: 'thumb'));

    expect($result->variantServed)->toBe('original');
});

it('logs access to a sensitive-category file (BR-CORE-10-007/AC-CORE-10-004)', function (): void {
    $school = School::factory()->create();
    $nurse = User::factory()->create();
    Queue::fake();
    $file = app(UploadFileAction::class)->execute(new UploadFileData(
        $school->id,
        'medical_report',
        "%PDF-1.4\n%fake pdf content for content-sniffing",
        'report.pdf',
        $nurse->id,
    ));

    app(GenerateSignedFileUrlAction::class)->execute(new GenerateSignedFileUrlData($file->id, $nurse->id, action: 'download'));

    $this->assertDatabaseHas('file_access_log', ['file_id' => $file->id, 'user_id' => $nurse->id, 'action' => 'download']);
});

it('soft-deletes a file rather than removing it immediately (BR-CORE-10-011)', function (): void {
    $school = School::factory()->create();
    $user = User::factory()->create();
    $file = app(UploadFileAction::class)->execute(new UploadFileData($school->id, 'signature', tinyPng(), 'a.png', $user->id));

    app(DeleteFileAction::class)->execute($file);

    // fresh() bypasses all scopes including SoftDeletingScope, so it
    // still finds the trashed row rather than returning null.
    expect($file->fresh()->trashed())->toBeTrue();
    $this->assertSoftDeleted('files', ['id' => $file->id]);
    expect(Storage::disk('local')->exists($file->path))->toBeTrue();
});
