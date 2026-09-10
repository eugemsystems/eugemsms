<?php

declare(strict_types=1);

namespace Modules\Core\Domain\DataObjects\Audit;

final readonly class ReviewSecurityEventData
{
    public function __construct(
        public int $securityEventId,
        public int $reviewedByUserId,
        public ?string $notes = null,
    ) {}
}
