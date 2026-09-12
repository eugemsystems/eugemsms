<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Actions\Backups;

use Modules\Core\Domain\Actions\Action;
use Modules\Core\Domain\Actions\Audit\RecordSecurityEventAction;
use Modules\Core\Domain\Actions\Files\UploadFileAction;
use Modules\Core\Domain\DataObjects\Audit\RecordSecurityEventData;
use Modules\Core\Domain\DataObjects\Backups\GenerateContractExitExportData;
use Modules\Core\Domain\DataObjects\Files\UploadFileData;
use Modules\Core\Domain\Support\Backups\TenantDataDumper;
use Modules\Core\Domain\Support\Imports\CsvWriter;
use Modules\Core\Models\File;
use RuntimeException;
use ZipArchive;

/**
 * ACT-GenerateContractExitExport (Book A CORE-13 §3/BR-CORE-13-008).
 * "All of a school's data in open formats (CSV, JSON, plus original
 * files)" — deliberately NOT the same artifact `CreateBackupAction`'s
 * `school_export` type produces (BR-CORE-13-007's logical export is one
 * encrypted, restore-oriented blob; this one is a plain zip a departing
 * customer can open with nothing but a spreadsheet application), even
 * though both dump identical rows via the same `TenantDataDumper` — the
 * difference is entirely in what happens to the dump afterward.
 *
 * The resulting zip is stored through the File Vault (CORE-10) under
 * its own `contract_exit_export` category rather than a bespoke
 * location, so it inherits signed-URL delivery, the sensitive-category
 * access log, and storage-quota accounting for free.
 */
final class GenerateContractExitExportAction extends Action
{
    protected bool $transactional = false;

    public function __construct(
        private readonly TenantDataDumper $tenantDataDumper,
        private readonly CsvWriter $csvWriter,
        private readonly UploadFileAction $uploadFile,
        private readonly RecordSecurityEventAction $recordSecurityEvent,
    ) {}

    public function execute(GenerateContractExitExportData $data): File
    {
        $tables = $this->tenantDataDumper->dumpTables($data->schoolId);
        $files = $this->tenantDataDumper->dumpFiles($data->schoolId);

        $zipContents = $this->buildZip($tables, $files, $data->schoolId);

        $exportFile = $this->uploadFile->execute(new UploadFileData(
            schoolId: $data->schoolId,
            category: 'contract_exit_export',
            contents: $zipContents,
            originalName: "contract-exit-export-school-{$data->schoolId}-".now()->format('Y-m-d').'.zip',
            uploadedByUserId: $data->requestedByUserId,
        ));

        $this->recordSecurityEvent->execute(new RecordSecurityEventData(
            eventType: 'contract_exit_export_generated',
            severity: 'warning',
            description: "Contract-exit export generated for school [{$data->schoolId}] — every table and file this school owns.",
            schoolId: $data->schoolId,
            userId: $data->requestedByUserId,
            context: ['file_id' => $exportFile->id, 'table_count' => count($tables), 'file_count' => count($files)],
        ));

        return $exportFile;
    }

    /**
     * @param  array<string, array<int, array<string, mixed>>>  $tables
     * @param  array<string, array<string, mixed>>  $files
     */
    private function buildZip(array $tables, array $files, int $schoolId): string
    {
        $tempPath = tempnam(sys_get_temp_dir(), 'contract-exit-');

        if ($tempPath === false) {
            throw new RuntimeException('Could not allocate a temporary file for the export archive.');
        }

        $zip = new ZipArchive;

        if ($zip->open($tempPath, ZipArchive::OVERWRITE) !== true) {
            unlink($tempPath);

            throw new RuntimeException('Could not create the export archive.');
        }

        $manifest = ['school_id' => $schoolId, 'generated_at' => now()->toIso8601String(), 'tables' => [], 'file_count' => 0];

        foreach ($tables as $table => $rows) {
            $manifest['tables'][$table] = count($rows);

            if ($rows !== []) {
                $zip->addFromString("tables/{$table}.csv", $this->csvWriter->write($rows));
            }
        }

        foreach ($files as $ulid => $file) {
            $zip->addFromString("files/{$ulid}-{$file['original_name']}", base64_decode((string) $file['contents_base64']));
            $manifest['file_count']++;
        }

        $zip->addFromString('manifest.json', json_encode($manifest, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
        $zip->close();

        $contents = file_get_contents($tempPath);
        unlink($tempPath);

        if ($contents === false) {
            throw new RuntimeException('Could not read back the export archive.');
        }

        return $contents;
    }
}
