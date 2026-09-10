<?php

declare(strict_types=1);

namespace Modules\Core\Domain\DataObjects\Files;

final readonly class RecordFileAccessData
{
    public function __construct(
        public int $fileId,
        public int $userId,
        public string $action,
        public ?string $ip = null,
    ) {}
}
