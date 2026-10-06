<?php

declare(strict_types=1);

namespace Modules\Core\Domain\DataObjects\Auth;

final readonly class StartVendorImpersonationData
{
    public function __construct(
        public int $operatorId,
        public int $targetUserId,
        public string $ticketReference,
        public string $reason,
    ) {}
}
