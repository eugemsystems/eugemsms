<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Actions\Backups;

use Modules\Core\Domain\Actions\Action;
use Modules\Core\Domain\DataObjects\Backups\CreateBackupData;
use Modules\Core\Domain\DataObjects\Backups\CreateSchoolExportData;
use Modules\Core\Models\Backup;

/**
 * ACT-CreateSchoolExport (Book A CORE-13 §3/BR-CORE-13-007/008). One
 * mechanism serves both business rules: a routine per-school logical
 * export and a contract-exit export are the same artifact (that
 * school's tenant-scoped rows plus its original files, in an
 * importable form) — they differ only in who asks and when, not in
 * what gets produced, so there is no separate "is this contract exit"
 * flag to track. Thin wrapper over `CreateBackupAction`'s
 * `school_export` type, which always includes both tables and files.
 */
final class CreateSchoolExportAction extends Action
{
    public function __construct(
        private readonly CreateBackupAction $createBackup,
    ) {}

    public function execute(CreateSchoolExportData $data): Backup
    {
        return $this->createBackup->execute(new CreateBackupData(
            type: 'school_export',
            triggeredBy: 'manual',
            schoolId: $data->schoolId,
            createdByUserId: $data->requestedByUserId,
        ));
    }
}
