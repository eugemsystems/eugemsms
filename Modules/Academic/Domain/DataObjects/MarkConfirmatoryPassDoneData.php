<?php

declare(strict_types=1);

namespace Modules\Academic\Domain\DataObjects;

final readonly class MarkConfirmatoryPassDoneData
{
    public function __construct(
        public int $stockTakeId,
    ) {}
}
