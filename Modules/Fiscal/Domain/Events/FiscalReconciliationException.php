<?php

declare(strict_types=1);

namespace Modules\Fiscal\Domain\Events;

final class FiscalReconciliationException
{
    /**
     * @param  array<int, array{source_type: string, source_id: int}>  $unreconciled
     */
    public function __construct(
        public readonly int $schoolId,
        public readonly array $unreconciled,
    ) {}
}
