<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Contracts\Install;

final readonly class SeedPackOutcome
{
    public function __construct(
        public string $packCode,
        public bool $ran,
        public string $message,
    ) {}
}
