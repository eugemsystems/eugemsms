<?php

declare(strict_types=1);

namespace Modules\Core\Domain\DataObjects\Scheduling;

final readonly class RunHealthChecksData
{
    /**
     * @param  array<int, string>|null  $checkKeys  null runs every registered, available check
     */
    public function __construct(
        public ?array $checkKeys = null,
    ) {}
}
