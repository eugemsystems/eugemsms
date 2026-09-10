<?php

use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Modules\Core\Domain\Actions\Backups\ApplyBackupRetentionAction;
use Modules\Core\Domain\Actions\Backups\CreateBackupAction;
use Modules\Core\Domain\Actions\Backups\CreateSchoolExportAction;
use Modules\Core\Domain\Actions\Files\UploadFileAction;
use Modules\Core\Domain\DataObjects\Backups\CreateBackupData;
use Modules\Core\Domain\DataObjects\Backups\CreateSchoolExportData;
use Modules\Core\Domain\DataObjects\Files\UploadFileData;
use Modules\Core\Models\AcademicYear;
use Modules\Core\Models\Backup;
use Modules\Core\Models\School;

it('creates a completed, encrypted, checksummed database backup (BR-CORE-13-001/002)', function (): void {
    $backup = app(CreateBackupAction::class)->execute(new CreateBackupData(type: 'database', triggeredBy: 'schedule'));

    expect($backup->status)->toBe('completed')
        ->and($backup->is_encrypted)->toBeTrue()
        ->and($backup->checksum)->not->toBeNull()
        ->and($backup->size_bytes)->toBeGreaterThan(0)
        ->and($backup->path)->not->toBe('');

    $stored = Storage::disk('backups')->get($backup->path);
    expect(hash('sha256', $stored))->toBe($backup->checksum);

    $payload = json_decode(Crypt::decryptString($stored), true);
    expect($payload['tables'])->toHaveKey('schools')
        ->and($payload)->not->toHaveKey('files');
});

it('dumps only this application\'s own tables, never the whole shared connection (BR-CORE-13-001)', function (): void {
    $backup = app(CreateBackupAction::class)->execute(new CreateBackupData(type: 'database', triggeredBy: 'manual'));

    $payload = json_decode(Crypt::decryptString(Storage::disk('backups')->get($backup->path)), true);

    expect(array_keys($payload['tables']))->not->toContain('wp_options', 'wp_users', 'at_options');
});

it('includes file bytes for a files backup but no table rows', function (): void {
    $school = School::factory()->create();
    $user = User::factory()->create();
    app(UploadFileAction::class)->execute(new UploadFileData(
        schoolId: $school->id,
        category: 'import_source_file',
        contents: 'fake-file-bytes',
        originalName: 'learners.csv',
        uploadedByUserId: $user->id,
    ));

    $backup = app(CreateBackupAction::class)->execute(new CreateBackupData(type: 'files', triggeredBy: 'manual'));

    $payload = json_decode(Crypt::decryptString(Storage::disk('backups')->get($backup->path)), true);

    expect($payload)->not->toHaveKey('tables')
        ->and($payload['files'])->toHaveCount(1)
        ->and(base64_decode(collect($payload['files'])->first()['contents_base64']))->toBe('fake-file-bytes');
});

it('requires a school_id for a school_export backup', function (): void {
    app(CreateBackupAction::class)->execute(new CreateBackupData(type: 'school_export', triggeredBy: 'manual'));
})->throws(ValidationException::class);

it('rejects a school_id for any backup type other than school_export', function (): void {
    $school = School::factory()->create();

    app(CreateBackupAction::class)->execute(new CreateBackupData(type: 'database', triggeredBy: 'manual', schoolId: $school->id));
})->throws(ValidationException::class);

it('scopes a school_export backup to one school\'s tenant tables and files only (BR-CORE-13-007)', function (): void {
    $schoolA = School::factory()->create();
    $schoolB = School::factory()->create();
    AcademicYear::factory()->for($schoolA)->create();
    AcademicYear::factory()->for($schoolB)->create();

    $backup = app(CreateSchoolExportAction::class)->execute(new CreateSchoolExportData(schoolId: $schoolA->id));

    expect($backup->type)->toBe('school_export')
        ->and($backup->scope)->toBe('school')
        ->and($backup->scope_id)->toBe($schoolA->id);

    $payload = json_decode(Crypt::decryptString(Storage::disk('backups')->get($backup->path)), true);
    $years = collect($payload['tables']['academic_years']);

    expect($years)->toHaveCount(1)
        ->and($years->first()['school_id'])->toBe($schoolA->id);
});

it('records a failed backup and alerts, without leaving the row stuck running', function (): void {
    Storage::shouldReceive('disk')->with('backups')->andThrow(new RuntimeException('disk unavailable'));

    $backup = app(CreateBackupAction::class)->execute(new CreateBackupData(type: 'database', triggeredBy: 'schedule'));

    expect($backup->status)->toBe('failed')
        ->and($backup->completed_at)->not->toBeNull();

    $this->assertDatabaseHas('security_events', ['event_type' => 'backup_failed', 'severity' => 'critical']);
});

it('escalates on two consecutive backup failures (BR-CORE-13-004)', function (): void {
    Storage::shouldReceive('disk')->with('backups')->andThrow(new RuntimeException('disk unavailable'));

    app(CreateBackupAction::class)->execute(new CreateBackupData(type: 'database', triggeredBy: 'schedule'));
    app(CreateBackupAction::class)->execute(new CreateBackupData(type: 'database', triggeredBy: 'schedule'));

    $this->assertDatabaseHas('security_events', ['event_type' => 'backup_failed_repeatedly', 'severity' => 'critical']);
});

it('applies grandfather-father-son retention, expiring backups outside every tier (BR-CORE-13-005)', function (): void {
    // Fixed historical dates, not relative to "today", so the tier math
    // is deterministic regardless of which day this test runs on: one
    // backup per calendar month, Jan 2020 through Mar 2021 (15 months).
    // Default monthly retention is 12, so the oldest 3 (Jan-Mar 2020)
    // fall outside every tier — the yearly tier (limit 7, only 2 distinct
    // years present) only ever rescues the single most-recent backup per
    // year, which the monthly tier already keeps.
    $dates = collect(range(0, 14))->map(fn (int $i) => Carbon::parse('2020-01-01')->addMonthsNoOverflow($i));

    $dates->each(fn ($date) => Backup::factory()->create([
        'status' => 'completed',
        'started_at' => $date,
        'completed_at' => $date,
    ]));

    app(ApplyBackupRetentionAction::class)->execute();

    expect(Backup::where('status', 'completed')->count())->toBe(12)
        ->and(Backup::where('status', 'expired')->count())->toBe(3)
        ->and(Backup::where('status', 'expired')->pluck('completed_at')->map(fn ($d) => $d->format('Y-m'))->sort()->values()->all())
        ->toBe(['2020-01', '2020-02', '2020-03']);
});

it('deletes the underlying file when a backup expires', function (): void {
    $dates = collect(range(0, 14))->map(fn (int $i) => Carbon::parse('2020-01-01')->addMonthsNoOverflow($i));

    $backups = $dates->map(fn ($date) => Backup::factory()->create([
        'status' => 'completed',
        'disk' => 'backups',
        'path' => "database/{$date->format('Y-m')}.enc",
        'started_at' => $date,
        'completed_at' => $date,
    ]));

    $backups->each(fn (Backup $backup) => Storage::disk('backups')->put($backup->path, 'contents'));

    app(ApplyBackupRetentionAction::class)->execute();

    // The oldest of the 15 (2020-01) falls outside every tier — see the
    // preceding test for why — and its file must be removed, not just
    // its row marked.
    $oldest = $backups->first();

    expect($oldest->fresh()->status)->toBe('expired')
        ->and(Storage::disk('backups')->exists($oldest->path))->toBeFalse();
});
