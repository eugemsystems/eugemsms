<?php

declare(strict_types=1);

namespace Modules\Core\Domain\DataObjects\Audit;

final readonly class RecordDataAccessData
{
    public function __construct(
        public int $schoolId,
        public int $userId,
        public string $accessType,
        public string $resourceType,
        public ?int $resourceId = null,
        public ?int $recordCount = null,
        public ?string $purpose = null,
        public ?string $ip = null,
    ) {}
}
