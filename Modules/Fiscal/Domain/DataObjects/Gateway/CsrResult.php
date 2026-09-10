<?php

declare(strict_types=1);

namespace Modules\Fiscal\Domain\DataObjects\Gateway;

final readonly class CsrResult
{
    public function __construct(
        public string $csrContent,
        public string $privateKeyRef,
    ) {}
}
