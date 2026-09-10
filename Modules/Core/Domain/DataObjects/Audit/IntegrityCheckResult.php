<?php

declare(strict_types=1);

namespace Modules\Core\Domain\DataObjects\Audit;

final readonly class IntegrityCheckResult
{
    /**
     * @param  array<int, array<string, mixed>>  $failureDetails
     */
    public function __construct(
        public string $status,
        public ?int $recordsChecked = null,
        public int $failuresFound = 0,
        public array $failureDetails = [],
    ) {}

    public function passed(): bool
    {
        return $this->status === 'passed';
    }
}
