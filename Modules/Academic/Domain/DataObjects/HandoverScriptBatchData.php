<?php

declare(strict_types=1);

namespace Modules\Academic\Domain\DataObjects;

final readonly class HandoverScriptBatchData
{
    public function __construct(
        public int $batchId,
        public int $toStaffId,
        public int $scriptCount,
        public string $action,
        public int $recordedByUserId,
        public ?string $discrepancyNote = null,
    ) {}
}
