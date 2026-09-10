<?php

declare(strict_types=1);

namespace Modules\Finance\Domain\DataObjects;

final readonly class RevealAndCloseTillSessionData
{
    public function __construct(
        public int $tillSessionId,
        public int $closedByUserId,
        public int $cashOverShortAccountId,
        public ?string $varianceReason = null,
        public ?int $supervisedByUserId = null,
    ) {}
}
