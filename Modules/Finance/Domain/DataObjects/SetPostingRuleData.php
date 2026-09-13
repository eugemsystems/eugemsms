<?php

declare(strict_types=1);

namespace Modules\Finance\Domain\DataObjects;

final readonly class SetPostingRuleData
{
    public function __construct(
        public int $schoolId,
        public string $eventKey,
        public ?int $debitAccountId = null,
        public ?int $creditAccountId = null,
        public ?string $debitResolver = null,
        public ?string $creditResolver = null,
        public ?int $costCentreId = null,
        public bool $isActive = true,
    ) {}
}
