<?php

declare(strict_types=1);

namespace Modules\Reporting\Domain\Events;

final class DuplicateAccountingExportAttempted
{
    public function __construct(
        public readonly int $schoolId,
        public readonly string $periodFrom,
        public readonly string $periodTo,
    ) {}
}
