<?php

use App\Models\User;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Modules\Core\Domain\Actions\Files\DeleteFileAction;
use Modules\Core\Domain\Actions\Files\PurgeExpiredSoftDeletedFilesAction;
use Modules\Core\Domain\Actions\Files\SetStorageQuotaAction;
use Modules\Core\Domain\Actions\Files\UploadFileAction;
use Modules\Core\Domain\DataObjects\Files\PurgeExpiredFilesData;
use Modules\Core\Domain\DataObjects\Files\SetStorageQuotaData;
use Modules\Core\Domain\DataObjects\Files\UploadFileData;
use Modules\Core\Domain\Support\Files\SignedFileUrlGenerator;
use Modules\Core\Models\AcademicYear;
use Modules\Core\Models\File;
use Modules\Core\Models\FinancialAuditLogEntry;
use Modules\Core\Models\School;

beforeEach(function (): void {
    Storage::fake('local');
    Queue::fake();
});

it('creates and updates a storage quota', function (): void {
    $school = School::factory()->create();

    $quota = app(SetStorageQuotaAction::class)->execute(new SetStorageQuotaData($school->id, 1_000_000));
    expect($quota->quota_bytes)->toBe(1_000_000);

    $updated = app(SetStorageQuotaAction::class)->execute(new SetStorageQuotaData($school->id, 2_000_000));
    expect($updated->id)->toBe($quota->id)
        ->and($updated->quota_bytes)->toBe(2_000_000);
});

it('physically purges a soft-deleted file once past its retention window', function (): void {
    $school = School::factory()->create();
    $user = User::factory()->create();
    $file = app(UploadFileAction::class)->execute(new UploadFileData($school->id, 'signature', base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII='), 'a.png', $user->id));
    app(DeleteFileAction::class)->execute($file);

    File::withTrashed()->whereKey($file->id)->update(['deleted_at' => now()->subDays(31)]);

    $purged = app(PurgeExpiredSoftDeletedFilesAction::class)->execute(new PurgeExpiredFilesData(retentionDays: 30));

    expect($purged)->toBe(1)
        ->and(Storage::disk('local')->exists($file->path))->toBeFalse()
        ->and(File::withTrashed()->find($file->id))->toBeNull();
});

it('never purges a file the financial audit log still names as its subject (BR-CORE-10-012)', function (): void {
    $school = School::factory()->create();
    $user = User::factory()->create();
    $file = app(UploadFileAction::class)->execute(new UploadFileData($school->id, 'signature', base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII='), 'a.png', $user->id));
    app(DeleteFileAction::class)->execute($file);
    File::withTrashed()->whereKey($file->id)->update(['deleted_at' => now()->subDays(31)]);

    FinancialAuditLogEntry::create([
        'school_id' => $school->id,
        'sequence' => 1,
        'event_type' => 'receipt_issued',
        'subject_type' => File::class,
        'subject_id' => $file->id,
        'payload' => ['note' => 'test'],
        'payload_hash' => hash('sha256', '{}'),
        'causer_id' => $user->id,
        'academic_year_id' => AcademicYear::factory()->for($school)->create()->id,
        'occurred_at' => now(),
    ]);

    $purged = app(PurgeExpiredSoftDeletedFilesAction::class)->execute(new PurgeExpiredFilesData(retentionDays: 30));

    expect($purged)->toBe(0)
        ->and(Storage::disk('local')->exists($file->path))->toBeTrue();
});

it('signs and verifies a file URL with an expiry (BR-CORE-10-003)', function (): void {
    $generator = new SignedFileUrlGenerator('test-secret-key');

    $url = $generator->generate('01FAKEULID', 'original', ttlMinutes: 5);

    parse_str((string) parse_url($url, PHP_URL_QUERY), $query);

    expect($generator->verify('01FAKEULID', 'original', (int) $query['expires'], $query['signature']))->toBeTrue()
        ->and($generator->verify('01FAKEULID', 'original', (int) $query['expires'], 'tampered'))->toBeFalse()
        ->and($generator->verify('01FAKEULID', 'original', now()->subMinute()->timestamp, $query['signature']))->toBeFalse();
});
