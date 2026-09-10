<?php

declare(strict_types=1);

namespace Modules\Finance\Domain\DataObjects;

use Modules\Core\Domain\Support\Money;

/**
 * One line of a journal being posted. `direction` is `'DR'` or `'CR'`;
 * `amount` is always positive — direction carries the sign
 * (BR-FIN-01-004).
 */
final readonly class JournalLineData
{
    public function __construct(
        public int $accountId,
        public string $direction,
        public Money $amount,
        public ?int $costCentreId = null,
        public ?string $subledgerType = null,
        public ?int $subledgerId = null,
        public ?string $narration = null,
    ) {}
}
