<?php

declare(strict_types=1);

namespace Modules\Boarding\Domain\DataObjects;

final readonly class BlacklistVisitorData
{
    public function __construct(
        public int $visitorId,
        public string $reason,
        public int $blacklistedByUserId,
    ) {}
}
