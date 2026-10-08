<?php

declare(strict_types=1);

namespace Modules\Academic\Domain\DataObjects;

final readonly class CompileProjectPortfolioData
{
    public function __construct(
        public int $learnerProjectId,
        public int $compiledByUserId,
    ) {}
}
