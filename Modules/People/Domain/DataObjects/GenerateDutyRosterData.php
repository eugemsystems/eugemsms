<?php

declare(strict_types=1);

namespace Modules\People\Domain\DataObjects;

final readonly class GenerateDutyRosterData
{
    /**
     * @param  array<int, DutySlot>  $slots
     */
    public function __construct(
        public int $rosterId,
        public array $slots,
    ) {}
}
