<?php

declare(strict_types=1);

namespace Modules\Finance\Domain\DataObjects;

final readonly class MatchBankStatementLineData
{
    public function __construct(
        public int $bankStatementLineId,
        public string $matchedType,
        public int $matchedId,
        public int $matchConfidence,
        public int $matchedByUserId,
    ) {}
}
