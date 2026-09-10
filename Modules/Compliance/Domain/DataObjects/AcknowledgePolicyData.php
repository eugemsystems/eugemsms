<?php

declare(strict_types=1);

namespace Modules\Compliance\Domain\DataObjects;

final readonly class AcknowledgePolicyData
{
    public function __construct(
        public int $policyId,
        public string $acknowledgedByType,
        public int $acknowledgedById,
        public string $method,
        public ?string $ipAddress = null,
    ) {}
}
