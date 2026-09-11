<?php

declare(strict_types=1);

namespace Modules\Saas\Domain\DataObjects;

use Illuminate\Support\Carbon;

final readonly class CreateSubscriptionData
{
    /**
     * @param  array<int, int>  $coveredSchoolIds
     */
    public function __construct(
        public int $tenantId,
        public int $planId,
        public array $coveredSchoolIds,
        public string $billingCurrency,
        public Carbon $currentPeriodStart,
        public Carbon $currentPeriodEnd,
        public string $status = 'trial',
        public ?Carbon $trialEndsAt = null,
        public ?int $learnerCountAtBilling = null,
    ) {}
}
