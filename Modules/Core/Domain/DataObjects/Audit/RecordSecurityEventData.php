<?php

declare(strict_types=1);

namespace Modules\Core\Domain\DataObjects\Audit;

final readonly class RecordSecurityEventData
{
    /**
     * @param  array<string, mixed>|null  $context
     */
    public function __construct(
        public string $eventType,
        public string $severity,
        public string $description,
        public ?int $schoolId = null,
        public ?int $userId = null,
        public ?array $context = null,
        public ?string $ip = null,
        public ?string $userAgent = null,
    ) {}
}
