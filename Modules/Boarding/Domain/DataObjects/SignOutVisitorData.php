<?php

declare(strict_types=1);

namespace Modules\Boarding\Domain\DataObjects;

final readonly class SignOutVisitorData
{
    public function __construct(
        public int $visitorLogId,
        public int $gateStaffUserId,
    ) {}
}
