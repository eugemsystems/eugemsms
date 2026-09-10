<?php

declare(strict_types=1);

namespace Modules\Core\Domain\DataObjects\Sessions;

final readonly class ReopenPeriodData
{
    public function __construct(
        public int $requestId,
        public int $approvedByUserId,
        public ?string $ipAddress = null,
    ) {}
}
