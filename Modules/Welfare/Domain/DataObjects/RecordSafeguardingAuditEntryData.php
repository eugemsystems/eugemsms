<?php

declare(strict_types=1);

namespace Modules\Welfare\Domain\DataObjects;

final readonly class RecordSafeguardingAuditEntryData
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(
        public int $schoolId,
        public string $eventType,
        public int $userId,
        public string $userRoleAtTime,
        public array $payload,
        public ?int $caseId = null,
        public ?int $concernId = null,
        public ?string $accessBasis = null,
        public ?string $ip = null,
        public ?string $userAgent = null,
    ) {}
}
