<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Actions\Files;

use Modules\Core\Domain\Actions\Action;
use Modules\Core\Domain\Actions\Audit\RecordSecurityEventAction;
use Modules\Core\Domain\DataObjects\Audit\RecordSecurityEventData;
use Modules\Core\Domain\DataObjects\Files\RecordScanResultData;
use Modules\Core\Domain\Events\Files\FileQuarantined;
use Modules\Core\Models\File;

/**
 * ACT-RecordScanResult (Book A CORE-10 BR-CORE-10-005). Called from
 * `ScanFileJob`. An infected result quarantines the file (its
 * `scan_status` alone already makes `File::isDownloadableBy()` refuse
 * everyone) and raises a critical security event via CORE-08 — the
 * same escalation path a broken audit chain uses.
 */
final class RecordScanResultAction extends Action
{
    public function __construct(
        private readonly RecordSecurityEventAction $recordSecurityEvent,
    ) {}

    public function execute(RecordScanResultData $data): File
    {
        $file = File::query()->findOrFail($data->fileId);

        return $this->transaction(function () use ($file, $data): File {
            $file->forceFill([
                'scan_status' => $data->result->status,
                'scan_result' => $data->result->detail,
            ])->save();

            if ($data->result->status === 'infected') {
                event(new FileQuarantined($file));

                $this->recordSecurityEvent->execute(new RecordSecurityEventData(
                    eventType: 'infected_file_upload',
                    severity: 'critical',
                    description: "Uploaded file [{$file->original_name}] failed virus scanning and has been quarantined.",
                    schoolId: $file->school_id,
                    userId: $file->uploaded_by,
                    context: ['file_id' => $file->id, 'detail' => $data->result->detail],
                ));
            }

            return $file;
        });
    }
}
