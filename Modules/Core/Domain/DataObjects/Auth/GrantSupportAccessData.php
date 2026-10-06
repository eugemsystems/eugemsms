<?php

declare(strict_types=1);

namespace Modules\Core\Domain\DataObjects\Auth;

final readonly class GrantSupportAccessData
{
    public function __construct(
        public int $tenantId,
        public int $grantedByUserId,
        public string $ticketReference,
        public string $reason,
        public int $durationHours,
    ) {}
}
