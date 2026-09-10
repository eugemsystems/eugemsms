<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Contracts\Install;

final readonly class BackupResult
{
    public function __construct(
        public bool $verified,
        public ?string $reference = null,
        public ?string $message = null,
    ) {}
}
