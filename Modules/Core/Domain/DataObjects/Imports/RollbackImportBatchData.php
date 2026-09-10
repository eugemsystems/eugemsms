<?php

declare(strict_types=1);

namespace Modules\Core\Domain\DataObjects\Imports;

final readonly class RollbackImportBatchData
{
    public function __construct(
        public int $batchId,
    ) {}
}
