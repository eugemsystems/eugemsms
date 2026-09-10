<?php

declare(strict_types=1);

namespace Modules\Core\Domain\DataObjects\Files;

final readonly class ScanResult
{
    public function __construct(
        public string $status,
        public ?string $detail = null,
    ) {}
}
