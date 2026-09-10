<?php

declare(strict_types=1);

namespace Modules\Core\Domain\DataObjects\Auth;

final readonly class StartImpersonationData
{
    public function __construct(
        public int $impersonatorId,
        public int $impersonatedId,
        public string $reason,
        public string $ticketReference,
        public ?string $consentReference = null,
        public ?int $schoolId = null,
    ) {}
}
