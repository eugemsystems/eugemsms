<?php

declare(strict_types=1);

namespace Modules\Saas\Domain\DataObjects;

final readonly class IssueLicenceKeyData
{
    public function __construct(
        public int $tenantId,
        public int $subscriptionId,
        public int $offlineGraceDays = 14,
    ) {}
}
