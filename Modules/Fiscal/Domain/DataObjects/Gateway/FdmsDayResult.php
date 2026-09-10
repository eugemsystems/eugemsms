<?php

declare(strict_types=1);

namespace Modules\Fiscal\Domain\DataObjects\Gateway;

final readonly class FdmsDayResult
{
    public function __construct(
        public bool $success,
        public string $fdmsStatus,
        public ?string $errorMessage = null,
    ) {}
}
