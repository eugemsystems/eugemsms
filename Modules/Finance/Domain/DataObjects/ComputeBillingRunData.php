<?php

declare(strict_types=1);

namespace Modules\Finance\Domain\DataObjects;

use Carbon\CarbonInterface;

final readonly class ComputeBillingRunData
{
    /**
     * @param  array<int, int>|null  $studentIds  explicit scope override — mainly for tests; takes precedence over $scopeFilter when both are given
     * @param  array{section_id?: int, grade_level_id?: int, class_id?: int, enrolment_type?: string}|null  $scopeFilter  `Finance\Billing\RunWizard`'s own scope step (Book B FIN-02 §7/§2 `billing_runs.scope_filter`) — narrows the school's active students by these `Student` columns; omitted/empty means every active student in the school.
     */
    public function __construct(
        public int $schoolId,
        public int $academicYearId,
        public int $termId,
        public int $computedByUserId,
        public ?array $studentIds = null,
        public ?CarbonInterface $billingDate = null,
        public ?array $scopeFilter = null,
    ) {}
}
