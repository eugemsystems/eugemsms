<?php

declare(strict_types=1);

namespace Modules\Core\Domain\DataObjects\Files;

final readonly class SignedFileUrlResult
{
    public function __construct(
        public string $url,
        public string $variantServed,
    ) {}
}
