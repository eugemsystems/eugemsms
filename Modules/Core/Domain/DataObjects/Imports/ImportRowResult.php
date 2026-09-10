<?php

declare(strict_types=1);

namespace Modules\Core\Domain\DataObjects\Imports;

final readonly class ImportRowResult
{
    public function __construct(
        public string $status,
        public ?string $createdType = null,
        public ?int $createdId = null,
        public ?string $message = null,
    ) {}
}
