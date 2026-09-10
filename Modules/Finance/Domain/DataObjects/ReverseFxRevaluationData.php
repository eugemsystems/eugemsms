<?php

declare(strict_types=1);

namespace Modules\Finance\Domain\DataObjects;

final readonly class ReverseFxRevaluationData
{
    public function __construct(
        public int $revaluationId,
        public string $reason,
        public int $reversedByUserId,
    ) {}
}
