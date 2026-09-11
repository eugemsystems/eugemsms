<?php

declare(strict_types=1);

namespace Modules\People\Domain\DataObjects;

final readonly class SyncEndowmentBudgetEnvelopeData
{
    public function __construct(
        public int $bursaryEndowmentId,
        public int $academicYearId,
    ) {}
}
