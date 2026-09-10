<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Actions\Files;

use Modules\Core\Domain\Actions\Action;
use Modules\Core\Domain\DataObjects\Files\RecordFileAccessData;
use Modules\Core\Models\FileAccessLogEntry;

/**
 * ACT-RecordFileAccess (Book A CORE-10 BR-CORE-10-007/AC-CORE-10-004).
 */
final class RecordFileAccessAction extends Action
{
    public function execute(RecordFileAccessData $data): FileAccessLogEntry
    {
        return $this->transaction(fn (): FileAccessLogEntry => FileAccessLogEntry::create([
            'file_id' => $data->fileId,
            'user_id' => $data->userId,
            'action' => $data->action,
            'ip_address' => $data->ip,
            'accessed_at' => now(),
        ]));
    }
}
