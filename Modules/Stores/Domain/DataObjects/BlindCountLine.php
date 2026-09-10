<?php

declare(strict_types=1);

namespace Modules\Stores\Domain\DataObjects;

/**
 * Book H1 FIN-09 §7 ⭐/AC-FIN-09-005. Deliberately has no
 * `systemQuantity` property — the count sheet payload cannot leak
 * what it doesn't carry.
 */
final readonly class BlindCountLine
{
    public function __construct(
        public int $lineId,
        public int $itemId,
        public string $itemName,
        public string $baseUnit,
    ) {}
}
