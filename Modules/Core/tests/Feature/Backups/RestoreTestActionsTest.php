<?php

use App\Models\User;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Storage;
use Modules\Core\Domain\Actions\Backups\ApproveProductionRestoreAction;
use Modules\Core\Domain\Actions\Backups\CreateBackupAction;
use Modules\Core\Domain\Actions\Backups\RequestProductionRestoreAction;
use Modules\Core\Domain\Actions\Backups\RunRestoreTestAction;
use Modules\Core\Domain\Actions\Scheduling\RunHealthChecksAction;
use Modules\Core\Domain\DataObjects\Backups\ApproveProductionRestoreData;
use Modules\Core\Domain\DataObjects\Backups\CreateBackupData;
use Modules\Core\Domain\DataObjects\Backups\RequestProductionRestoreData;
use Modules\Core\Domain\DataObjects\Backups\RunRestoreTestData;
use Modules\Core\Domain\DataObjects\Scheduling\RunHealthChecksData;
use Modules\Core\Domain\Exceptions\InvalidStateTransitionException;
use Modules\Core\Domain\Support\Backups\BackupFreshnessHealthCheck;
use Modules\Core\Domain\Support\Backups\RestoreVerificationHealthCheck;
use Modules\Core\Models\Backup;
use Modules\Core\Models\SystemHealthCheck;

function createCompletedBackup(): Backup
{
    return app(CreateBackupAction::class)->execute(new CreateBackupData(type: 'database', triggeredBy: 'manual'));
}

it('passes a restore test against a genuine backup and marks it verified (BR-CORE-13-003)', function (): void {
    $backup = createCompletedBackup();

    $restoreTest = app(RunRestoreTestAction::class)->execute(new RunRestoreTestData(backupId: $backup->id));

    expect($restoreTest->status)->toBe('passed')
        ->and($restoreTest->checks_performed['checksum']['match'])->toBeTrue()
        ->and($restoreTest->checks_performed['structural_integrity']['match'])->toBeTrue();

    expect($backup->fresh()->isVerified())->toBeTrue()
        ->and($backup->fresh()->status)->toBe('verified');
});

it('fails a restore test when the stored backup file is corrupted', function (): void {
    $backup = createCompletedBackup();

    Storage::disk($backup->disk)->put($backup->path, 'corrupted-not-encrypted-data');

    $restoreTest = app(RunRestoreTestAction::class)->execute(new RunRestoreTestData(backupId: $backup->id));

    expect($restoreTest->status)->toBe('failed')
        ->and($backup->fresh()->isVerified())->toBeFalse();

    $this->assertDatabaseHas('security_events', ['event_type' => 'restore_test_failed', 'severity' => 'critical']);
});

it('fails a restore test when the payload is missing a table the manifest promised', function (): void {
    $backup = createCompletedBackup();

    $payload = json_decode(Crypt::decryptString(Storage::disk($backup->disk)->get($backup->path)), true);
    unset($payload['tables']['schools']);
    $tampered = Crypt::encryptString(json_encode($payload));
    Storage::disk($backup->disk)->put($backup->path, $tampered);
    $backup->update(['checksum' => hash('sha256', $tampered)]);

    $restoreTest = app(RunRestoreTestAction::class)->execute(new RunRestoreTestData(backupId: $backup->id));

    expect($restoreTest->status)->toBe('failed')
        ->and($restoreTest->checks_performed['structural_integrity']['missing_tables'])->toContain('schools');
});

it('escalates on two consecutive restore test failures (BR-CORE-13-004)', function (): void {
    $backupA = createCompletedBackup();
    Storage::disk($backupA->disk)->put($backupA->path, 'corrupted');
    app(RunRestoreTestAction::class)->execute(new RunRestoreTestData(backupId: $backupA->id));

    $backupB = createCompletedBackup();
    Storage::disk($backupB->disk)->put($backupB->path, 'corrupted');
    app(RunRestoreTestAction::class)->execute(new RunRestoreTestData(backupId: $backupB->id));

    $this->assertDatabaseHas('security_events', ['event_type' => 'restore_test_failed_repeatedly']);
});

it('refuses to restore-test a backup that never completed', function (): void {
    $backup = Backup::factory()->create(['status' => 'running']);

    app(RunRestoreTestAction::class)->execute(new RunRestoreTestData(backupId: $backup->id));
})->throws(InvalidStateTransitionException::class);

it('requires dual authorisation for a production restore, requester and approver differing (BR-CORE-13-009)', function (): void {
    $backup = createCompletedBackup();
    $requester = User::factory()->create();
    $approver = User::factory()->create();

    $request = app(RequestProductionRestoreAction::class)->execute(new RequestProductionRestoreData(
        backupId: $backup->id,
        requestedByUserId: $requester->id,
        reason: 'Disaster recovery drill.',
    ));

    expect($request->status)->toBe('pending_approval')
        ->and($request->target_environment)->toBe('production');

    $approved = app(ApproveProductionRestoreAction::class)->execute(new ApproveProductionRestoreData(
        restoreTestId: $request->id,
        approvedByUserId: $approver->id,
    ));

    expect($approved->status)->toBe('approved')
        ->and($approved->approved_by)->toBe($approver->id);
});

it('refuses to let the requester approve their own production restore', function (): void {
    $backup = createCompletedBackup();
    $requester = User::factory()->create();

    $request = app(RequestProductionRestoreAction::class)->execute(new RequestProductionRestoreData(
        backupId: $backup->id,
        requestedByUserId: $requester->id,
        reason: 'Disaster recovery drill.',
    ));

    app(ApproveProductionRestoreAction::class)->execute(new ApproveProductionRestoreData(
        restoreTestId: $request->id,
        approvedByUserId: $requester->id,
    ));
})->throws(InvalidStateTransitionException::class);

it('reports unhealthy on backup freshness when no backup has ever completed (BR-CORE-13-010)', function (): void {
    expect((new BackupFreshnessHealthCheck)->run()->status)->toBe('unhealthy');
});

it('reports healthy on backup freshness right after a successful backup', function (): void {
    createCompletedBackup();

    expect((new BackupFreshnessHealthCheck)->run()->status)->toBe('healthy');
});

it('reports unhealthy on restore verification when no restore test has ever passed (BR-CORE-13-003/010)', function (): void {
    expect((new RestoreVerificationHealthCheck)->run()->status)->toBe('unhealthy');
});

it('reports healthy on restore verification right after a passing restore test', function (): void {
    $backup = createCompletedBackup();
    app(RunRestoreTestAction::class)->execute(new RunRestoreTestData(backupId: $backup->id));

    expect((new RestoreVerificationHealthCheck)->run()->status)->toBe('healthy');
});

it('surfaces backup health on the shared CORE-12 health check dashboard', function (): void {
    createCompletedBackup();

    app(RunHealthChecksAction::class)->execute(new RunHealthChecksData(checkKeys: ['backup_last_success', 'backup_last_verified_restore']));

    expect(SystemHealthCheck::where('check_key', 'backup_last_success')->value('status'))->toBe('healthy')
        ->and(SystemHealthCheck::where('check_key', 'backup_last_verified_restore')->value('status'))->toBe('unhealthy');
});
