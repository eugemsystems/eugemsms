<?php

declare(strict_types=1);

namespace Modules\Finance\Domain\DataObjects;

use Carbon\CarbonInterface;

/**
 * BR-FIN-01-025: posts `OPENING_BALANCE` journals; the whole import is
 * atomic and rejected if the resulting trial balance doesn't balance.
 */
final readonly class ImportOpeningBalancesData
{
    /**
     * @param  array<int, OpeningBalanceLineData>  $lines
     */
    public function __construct(
        public int $schoolId,
        public int $academicYearId,
        public int $termId,
        public array $lines,
        public CarbonInterface $effectiveAt,
        public int $importedByUserId,
    ) {}
}
