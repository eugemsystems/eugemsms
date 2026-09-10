<?php

declare(strict_types=1);

namespace Modules\Comms\Domain\DataObjects;

final readonly class AutomationScanRecord
{
    /**
     * @param  array<string, mixed>  $fields  dotted field name => value, matching the entity's own allowedFields
     * @param  array<string, mixed>  $context  what the notification template may reference
     * @param  array<string, string>  $addresses  channel => address for the resolved recipient
     */
    public function __construct(
        public int $schoolId,
        public string $subjectType,
        public int $subjectId,
        public array $fields,
        public array $context,
        public string $recipientType,
        public ?int $recipientId,
        public array $addresses,
    ) {}
}
