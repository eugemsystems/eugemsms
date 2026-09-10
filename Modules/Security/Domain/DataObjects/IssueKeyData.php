<?php

declare(strict_types=1);

namespace Modules\Security\Domain\DataObjects;

use Carbon\CarbonInterface;

final readonly class IssueKeyData
{
    public function __construct(
        public int $keyId,
        public int $issuedByUserId,
        public ?int $issuedToStaffId = null,
        public ?int $issuedToContractorId = null,
        public ?CarbonInterface $dueBackOn = null,
        public bool $higherAuthorityConfirmed = false,
    ) {}
}
