<?php

declare(strict_types=1);

namespace Modules\Reporting\Domain\DataObjects;

final readonly class AcknowledgeCloseCheckData
{
    public function __construct(
        public int $checklistId,
        public string $checkKey,
        public string $reason,
        public int $acknowledgedByUserId,
        public ?int $approvedByUserId = null,
    ) {}
}
