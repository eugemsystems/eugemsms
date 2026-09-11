<?php

declare(strict_types=1);

namespace Modules\Saas\Domain\DataObjects;

final readonly class RaiseSupportTicketData
{
    public function __construct(
        public int $tenantId,
        public ?int $schoolId,
        public int $raisedByUserId,
        public string $subject,
        public string $description,
        public string $category,
        public string $priority,
    ) {}
}
