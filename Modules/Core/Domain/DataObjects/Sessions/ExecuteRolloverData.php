<?php

declare(strict_types=1);

namespace Modules\Core\Domain\DataObjects\Sessions;

final readonly class ExecuteRolloverData
{
    public function __construct(
        public int $rolloverId,
        public int $approvedByUserId,
    ) {}
}
