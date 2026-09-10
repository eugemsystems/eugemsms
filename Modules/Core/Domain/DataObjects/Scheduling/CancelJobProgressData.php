<?php

declare(strict_types=1);

namespace Modules\Core\Domain\DataObjects\Scheduling;

final readonly class CancelJobProgressData
{
    public function __construct(
        public int $jobProgressId,
    ) {}
}
