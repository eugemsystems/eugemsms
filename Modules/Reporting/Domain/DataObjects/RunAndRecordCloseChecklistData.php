<?php

declare(strict_types=1);

namespace Modules\Reporting\Domain\DataObjects;

final readonly class RunAndRecordCloseChecklistData
{
    public function __construct(
        public int $termId,
        public string $periodType,
        public int $runByUserId,
    ) {}
}
