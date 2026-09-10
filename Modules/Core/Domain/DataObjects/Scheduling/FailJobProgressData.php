<?php

declare(strict_types=1);

namespace Modules\Core\Domain\DataObjects\Scheduling;

final readonly class FailJobProgressData
{
    public function __construct(
        public int $jobProgressId,
        public string $error,
    ) {}
}
