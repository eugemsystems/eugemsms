<?php

declare(strict_types=1);

namespace Modules\Core\Domain\DataObjects\Imports;

final readonly class ExecuteImportBatchData
{
    public function __construct(
        public int $batchId,
        public bool $approved,
    ) {}
}
