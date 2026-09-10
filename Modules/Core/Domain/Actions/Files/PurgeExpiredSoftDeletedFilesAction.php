<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Actions\Files;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Modules\Core\Domain\Actions\Action;
use Modules\Core\Domain\DataObjects\Files\PurgeExpiredFilesData;
use Modules\Core\Models\File;
use Modules\Core\Models\FinancialAuditLogEntry;

/**
 * ACT-PurgeExpiredSoftDeletedFiles (Book A CORE-10 BR-CORE-10-011/012).
 * Only physically removes a soft-deleted file once
 * `soft_delete_retention_days` has elapsed, and never one that
 * `financial_audit_log` still names as its subject — the one
 * cross-module reference this module can actually check today.
 *
 * @return int number of files physically removed
 */
final class PurgeExpiredSoftDeletedFilesAction extends Action
{
    public function execute(PurgeExpiredFilesData $data): int
    {
        $cutoff = Carbon::now()->subDays($data->retentionDays);
        $purged = 0;

        File::onlyTrashed()
            ->where('deleted_at', '<=', $cutoff)
            ->each(function (File $file) use (&$purged): void {
                $isReferencedInFinancialAudit = FinancialAuditLogEntry::query()
                    ->where('subject_type', File::class)
                    ->where('subject_id', $file->id)
                    ->exists();

                if ($isReferencedInFinancialAudit) {
                    return;
                }

                $this->transaction(function () use ($file): void {
                    Storage::disk($file->disk)->delete($file->path);

                    foreach ($file->variants ?? [] as $variantPath) {
                        Storage::disk($file->disk)->delete($variantPath);
                    }

                    $file->forceDelete();
                });

                $purged++;
            });

        return $purged;
    }
}
