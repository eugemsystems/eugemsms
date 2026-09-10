<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Actions\Files;

use Modules\Core\Domain\Actions\Action;
use Modules\Core\Domain\DataObjects\Files\SetStorageQuotaData;
use Modules\Core\Models\StorageQuota;

final class SetStorageQuotaAction extends Action
{
    public function execute(SetStorageQuotaData $data): StorageQuota
    {
        return $this->transaction(fn (): StorageQuota => StorageQuota::updateOrCreate(
            ['school_id' => $data->schoolId],
            ['quota_bytes' => $data->quotaBytes, 'warn_at_percent' => $data->warnAtPercent],
        ));
    }
}
