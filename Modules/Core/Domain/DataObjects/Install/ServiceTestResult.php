<?php

declare(strict_types=1);

namespace Modules\Core\Domain\DataObjects\Install;

final readonly class ServiceTestResult
{
    public function __construct(
        public bool $success,
        public string $message,
    ) {}
}
