<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Actions\Backups;

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Storage;
use Modules\Core\Domain\Actions\Action;
use Modules\Core\Domain\Actions\Audit\RecordSecurityEventAction;
use Modules\Core\Domain\DataObjects\Audit\RecordSecurityEventData;
use Modules\Core\Domain\DataObjects\Backups\RunRestoreTestData;
use Modules\Core\Domain\Exceptions\InvalidStateTransitionException;
use Modules\Core\Models\Backup;
use Modules\Core\Models\RestoreTest;
use Throwable;

/**
 * ACT-RunRestoreTest (Book A CORE-13 §3 ⭐ BR-CORE-13-003). "A backup
 * that has never been test-restored is not a backup." This engine does
 * not spin up a genuinely separate physical environment and restore
 * into it — that needs real infrastructure this build doesn't have.
 * What it does for real: decrypts the backup exactly as a true restore
 * would, parses the payload, and proves it is what it claims to be —
 * every table the backup's own manifest promised is present, and each
 * one's row count matches what was recorded at backup time. That
 * catches truncation, corruption, and a broken encryption round-trip,
 * which is most of what a restore test exists to catch; it cannot
 * catch a bug in `CreateBackupAction` that recorded a wrong count in
 * the first place. Trial balance verification is deferred to FIN-01,
 * the same way `IntegrityCheckRegistry`'s trial-balance check is.
 */
final class RunRestoreTestAction extends Action
{
    public function __construct(
        private readonly RecordSecurityEventAction $recordSecurityEvent,
    ) {}

    public function execute(RunRestoreTestData $data): RestoreTest
    {
        $backup = Backup::findOrFail($data->backupId);

        if ($backup->status !== 'completed' && $backup->status !== 'verified') {
            throw new InvalidStateTransitionException(
                "Only a completed backup can be restore-tested — this one is {$backup->status}.",
                ['backup_id' => $backup->id],
            );
        }

        $startedAt = microtime(true);
        $checks = [];
        $error = null;

        try {
            $encrypted = Storage::disk($backup->disk)->get($backup->path);

            if ($encrypted === null) {
                throw new DecryptException("Backup file not found at [{$backup->path}].");
            }

            $checks['checksum'] = [
                'expected' => $backup->checksum,
                'actual' => hash('sha256', $encrypted),
                'match' => hash('sha256', $encrypted) === $backup->checksum,
            ];

            $json = $backup->is_encrypted ? Crypt::decryptString($encrypted) : $encrypted;
            $payload = json_decode($json, true, flags: JSON_THROW_ON_ERROR);

            $expectedCounts = $payload['manifest']['tables'] ?? [];
            $rowCounts = [];
            $allMatch = $checks['checksum']['match'];

            foreach ($expectedCounts as $table => $expected) {
                $actual = count($payload['tables'][$table] ?? []);
                $match = $actual === $expected;
                $rowCounts[$table] = ['expected' => $expected, 'actual' => $actual, 'match' => $match];
                $allMatch = $allMatch && $match;
            }

            $missingTables = array_values(array_diff(array_keys($expectedCounts), array_keys($payload['tables'] ?? [])));
            $allMatch = $allMatch && $missingTables === [];

            $checks['row_counts'] = $rowCounts;
            $checks['structural_integrity'] = ['missing_tables' => $missingTables, 'match' => $missingTables === []];
            $checks['trial_balance'] = ['status' => 'skipped', 'reason' => 'Not yet available — ships with FIN-01.'];

            $status = $allMatch ? 'passed' : 'failed';
        } catch (Throwable $e) {
            $status = 'failed';
            $error = $e->getMessage();
        }

        $durationSeconds = (int) round(microtime(true) - $startedAt);

        $restoreTest = $this->transaction(fn (): RestoreTest => RestoreTest::create([
            'backup_id' => $backup->id,
            'status' => $status,
            'target_environment' => $data->targetEnvironment,
            'checks_performed' => $checks,
            'duration_seconds' => $durationSeconds,
            'error' => $error,
            'tested_at' => now(),
        ]));

        if ($status === 'passed') {
            $backup->update([
                'verified_at' => now(),
                'verification_notes' => 'Restore test passed.',
                'status' => 'verified',
            ]);
        } else {
            $this->alertFailure($restoreTest);
        }

        return $restoreTest;
    }

    private function alertFailure(RestoreTest $restoreTest): void
    {
        $this->recordSecurityEvent->execute(new RecordSecurityEventData(
            eventType: 'restore_test_failed',
            severity: 'critical',
            description: "Restore test for backup [{$restoreTest->backup_id}] failed: ".($restoreTest->error ?? 'checks did not pass'),
            context: ['restore_test_id' => $restoreTest->id, 'backup_id' => $restoreTest->backup_id],
        ));

        $previousStatus = RestoreTest::query()
            ->where('id', '!=', $restoreTest->id)
            ->orderByDesc('id')
            ->value('status');

        if ($previousStatus === 'failed') {
            $this->recordSecurityEvent->execute(new RecordSecurityEventData(
                eventType: 'restore_test_failed_repeatedly',
                severity: 'critical',
                description: 'Two consecutive restore tests have failed — escalating to the vendor operations lead (BR-CORE-13-004).',
                context: ['restore_test_id' => $restoreTest->id],
            ));
        }
    }
}
