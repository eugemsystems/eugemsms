<?php

declare(strict_types=1);

namespace Modules\Core\Domain\DataObjects\Sessions;

final readonly class RollbackRolloverData
{
    public function __construct(
        public int $rolloverId,
        public int $performedByUserId,
        public string $reason,
    ) {}
}
