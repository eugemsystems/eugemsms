<?php

declare(strict_types=1);

namespace Modules\Core\Domain\DataObjects\Scheduling;

final readonly class CompleteJobProgressData
{
    /**
     * @param  array<string, mixed>|null  $result
     */
    public function __construct(
        public int $jobProgressId,
        public ?array $result = null,
    ) {}
}
