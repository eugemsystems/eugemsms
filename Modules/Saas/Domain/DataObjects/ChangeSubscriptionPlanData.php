<?php

declare(strict_types=1);

namespace Modules\Saas\Domain\DataObjects;

final readonly class ChangeSubscriptionPlanData
{
    public function __construct(
        public int $subscriptionId,
        public int $newPlanId,
        public ?int $performedBy = null,
        public ?string $reason = null,
    ) {}
}
