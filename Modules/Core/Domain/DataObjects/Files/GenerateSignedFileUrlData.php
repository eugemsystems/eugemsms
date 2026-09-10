<?php

declare(strict_types=1);

namespace Modules\Core\Domain\DataObjects\Files;

final readonly class GenerateSignedFileUrlData
{
    public function __construct(
        public int $fileId,
        public int $requestedByUserId,
        public string $variant = 'original',
        public string $action = 'view',
        public ?string $ip = null,
    ) {}
}
