<?php

declare(strict_types=1);

namespace Modules\Fiscal\Domain\DataObjects\Gateway;

final readonly class FiscalHealthStatus
{
    public function __construct(
        public string $status,
        public ?string $message = null,
    ) {}
}
