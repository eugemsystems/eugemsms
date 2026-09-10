<?php

declare(strict_types=1);

namespace Modules\Finance\Domain\DataObjects\Gateway;

final readonly class HealthStatus
{
    public function __construct(
        public string $status,
        public ?string $message = null,
    ) {}
}
