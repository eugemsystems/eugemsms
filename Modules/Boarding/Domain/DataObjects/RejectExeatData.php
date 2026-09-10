<?php

declare(strict_types=1);

namespace Modules\Boarding\Domain\DataObjects;

final readonly class RejectExeatData
{
    public function __construct(
        public int $exeatId,
        public string $rejectionReason,
        public int $rejectedByUserId,
    ) {}
}
