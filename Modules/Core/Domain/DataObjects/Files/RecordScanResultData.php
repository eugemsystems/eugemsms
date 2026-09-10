<?php

declare(strict_types=1);

namespace Modules\Core\Domain\DataObjects\Files;

final readonly class RecordScanResultData
{
    public function __construct(
        public int $fileId,
        public ScanResult $result,
    ) {}
}
