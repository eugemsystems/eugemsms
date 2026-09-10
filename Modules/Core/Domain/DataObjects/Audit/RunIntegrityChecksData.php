<?php

declare(strict_types=1);

namespace Modules\Core\Domain\DataObjects\Audit;

final readonly class RunIntegrityChecksData
{
    /**
     * @param  array<int, string>|null  $checkTypes  null runs every registered, available check
     */
    public function __construct(
        public ?int $schoolId = null,
        public ?array $checkTypes = null,
    ) {}
}
