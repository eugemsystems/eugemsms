<?php

declare(strict_types=1);

namespace Modules\Core\Domain\DataObjects\Schools;

final readonly class BulkResult
{
    /**
     * @param  array<int, int>  $createdClassIds
     */
    public function __construct(
        public int $createdCount,
        public array $createdClassIds,
    ) {}
}
