<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Actions\Backups;

use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Modules\Core\Domain\Actions\Action;
use Modules\Core\Domain\Actions\Audit\RecordSecurityEventAction;
use Modules\Core\Domain\DataObjects\Audit\RecordSecurityEventData;
use Modules\Core\Domain\DataObjects\Backups\CreateBackupData;
use Modules\Core\Domain\Registry\TenantModelRegistry;
use Modules\Core\Domain\Support\Backups\OwnedTableScanner;
use Modules\Core\Models\Backup;
use Modules\Core\Models\File;
use RuntimeException;
use Throwable;

/**
 * ACT-CreateBackup (Book A CORE-13 §2). Produces a logical, encrypted
 * JSON dump on the `backups` disk — this codebase's own stand-in for a
 * native `mysqldump`/managed-snapshot pipeline (same "real local disk,
 * real infra later" substitution CORE-10 made for file storage), chosen
 * because it needs no new binary dependency and stays fully portable
 * across the MySQL/PostgreSQL choice Book A §0 leaves open. `type`
 * governs what's captured: `database` (every table this app owns, no
 * file bytes), `files` (uploaded file bytes only), `full` (both),
 * `school_export` (one school's tenant-scoped tables and files —
 * BR-CORE-13-007/008).
 *
 * Not transactional in the Action-base sense: this reaches the
 * filesystem, and a `Backup` row must exist and reflect the outcome
 * (including a crash mid-write) rather than roll back with it.
 */
final class CreateBackupAction extends Action
{
    protected bool $transactional = false;

    private const array VALID_TYPES = ['database', 'files', 'full', 'school_export'];

    private const array VALID_TRIGGERS = ['schedule', 'manual', 'pre_upgrade'];

    public function __construct(
        private readonly RecordSecurityEventAction $recordSecurityEvent,
    ) {}

    public function execute(CreateBackupData $data): Backup
    {
        $this->validate($data);

        $scope = $data->type === 'school_export' ? 'school' : 'system';

        $backup = Backup::create([
            'type' => $data->type,
            'scope' => $scope,
            'scope_id' => $data->schoolId,
            'disk' => 'backups',
            'path' => '',
            'is_encrypted' => true,
            'status' => 'running',
            'started_at' => now(),
            'triggered_by' => $data->triggeredBy,
            'created_by' => $data->createdByUserId,
        ]);

        try {
            $json = json_encode($this->buildPayload($data->type, $data->schoolId), JSON_THROW_ON_ERROR);
            $encrypted = Crypt::encryptString($json);
            $checksum = hash('sha256', $encrypted);
            $path = "{$data->type}/{$backup->ulid}.enc";

            Storage::disk('backups')->put($path, $encrypted);

            // BR-CORE-13-002: verified immediately after upload — re-read
            // what was actually written rather than trusting put()'s
            // return value.
            $stored = Storage::disk('backups')->get($path);

            if ($stored === null || hash('sha256', $stored) !== $checksum) {
                throw new RuntimeException('Backup checksum mismatch immediately after upload.');
            }

            $backup->update([
                'path' => $path,
                'size_bytes' => strlen($encrypted),
                'checksum' => $checksum,
                'status' => 'completed',
                'completed_at' => now(),
            ]);
        } catch (Throwable $e) {
            $backup->update([
                'status' => 'failed',
                'completed_at' => now(),
                'verification_notes' => $e->getMessage(),
            ]);

            $this->alertFailure($backup);
        }

        return $backup->refresh();
    }

    private function validate(CreateBackupData $data): void
    {
        if (! in_array($data->type, self::VALID_TYPES, true)) {
            throw ValidationException::withMessages(['type' => "Invalid backup type [{$data->type}]."]);
        }

        if (! in_array($data->triggeredBy, self::VALID_TRIGGERS, true)) {
            throw ValidationException::withMessages(['triggered_by' => "Invalid triggered_by [{$data->triggeredBy}]."]);
        }

        if ($data->type === 'school_export' && $data->schoolId === null) {
            throw ValidationException::withMessages(['school_id' => 'A school_export backup requires a school_id.']);
        }

        if ($data->type !== 'school_export' && $data->schoolId !== null) {
            throw ValidationException::withMessages(['school_id' => 'Only a school_export backup may carry a school_id.']);
        }
    }

    /**
     * @return array{meta: array<string, mixed>, tables?: array<string, array<int, array<string, mixed>>>, files?: array<string, array<string, mixed>>, manifest: array<string, mixed>}
     */
    private function buildPayload(string $type, ?int $schoolId): array
    {
        $payload = [
            'meta' => [
                'type' => $type,
                'school_id' => $schoolId,
                'generated_at' => now()->toIso8601String(),
            ],
        ];

        if (in_array($type, ['database', 'full', 'school_export'], true)) {
            $payload['tables'] = $this->dumpTables($schoolId);
        }

        if (in_array($type, ['files', 'full', 'school_export'], true)) {
            $payload['files'] = $this->dumpFiles($schoolId);
        }

        $payload['manifest'] = [
            'tables' => isset($payload['tables']) ? array_map(fn (array $rows): int => count($rows), $payload['tables']) : [],
            'file_count' => isset($payload['files']) ? count($payload['files']) : 0,
        ];

        return $payload;
    }

    /**
     * @return array<string, array<int, array<string, mixed>>>
     */
    private function dumpTables(?int $schoolId): array
    {
        $dump = [];

        if ($schoolId === null) {
            foreach (OwnedTableScanner::names() as $table) {
                if (! Schema::hasTable($table)) {
                    continue;
                }

                $dump[$table] = DB::table($table)->get()->map(fn (object $row): array => (array) $row)->all();
            }

            return $dump;
        }

        foreach (array_keys(TenantModelRegistry::all()) as $modelClass) {
            $model = new $modelClass;

            $dump[$model->getTable()] = $modelClass::withoutGlobalScopes()
                ->where('school_id', $schoolId)
                ->get()
                ->map(fn ($row): array => $row->toArray())
                ->all();
        }

        return $dump;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function dumpFiles(?int $schoolId): array
    {
        $query = File::query();

        if ($schoolId !== null) {
            $query->where('school_id', $schoolId);
        }

        $dump = [];

        foreach ($query->get() as $file) {
            try {
                $contents = Storage::disk($file->disk)->get($file->path);
            } catch (Throwable) {
                continue;
            }

            if ($contents === null) {
                continue;
            }

            $dump[$file->ulid] = [
                'original_name' => $file->original_name,
                'mime_type' => $file->mime_type,
                'school_id' => $file->school_id,
                'contents_base64' => base64_encode($contents),
            ];
        }

        return $dump;
    }

    private function alertFailure(Backup $backup): void
    {
        $alertSchoolId = $backup->scope === 'school' ? $backup->scope_id : null;

        $this->recordSecurityEvent->execute(new RecordSecurityEventData(
            eventType: 'backup_failed',
            severity: 'critical',
            description: "Backup [{$backup->type}/{$backup->scope}] failed: ".($backup->verification_notes ?? 'unknown error'),
            schoolId: $alertSchoolId,
            context: ['backup_id' => $backup->id, 'type' => $backup->type, 'scope' => $backup->scope],
        ));

        $previousStatus = Backup::query()
            ->where('type', $backup->type)
            ->where('scope', $backup->scope)
            ->where('id', '!=', $backup->id)
            ->when(
                $backup->scope_id === null,
                fn ($q) => $q->whereNull('scope_id'),
                fn ($q) => $q->where('scope_id', $backup->scope_id),
            )
            ->orderByDesc('id')
            ->value('status');

        if ($previousStatus === 'failed') {
            $this->recordSecurityEvent->execute(new RecordSecurityEventData(
                eventType: 'backup_failed_repeatedly',
                severity: 'critical',
                description: "Two consecutive [{$backup->type}/{$backup->scope}] backups have failed — escalating to the vendor operations lead (BR-CORE-13-004).",
                schoolId: $alertSchoolId,
                context: ['backup_id' => $backup->id],
            ));
        }
    }
}
