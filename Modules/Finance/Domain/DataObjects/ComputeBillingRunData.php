<?php

declare(strict_types=1);

namespace Modules\Finance\Domain\DataObjects;

use Carbon\CarbonInterface;

final readonly class ComputeBillingRunData
{
    /**
     * @param  array<int, int>|null  $studentIds  explicit scope override — mainly for tests; production scoping is via scopeFilter attributes (deferred to the run wizard screen)
     */
    public function __construct(
        public int $schoolId,
        public int $academicYearId,
        public int $termId,
        public int $computedByUserId,
        public ?array $studentIds = null,
        public ?CarbonInterface $billingDate = null,
    ) {}
}
