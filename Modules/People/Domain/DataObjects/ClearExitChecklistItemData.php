<?php

declare(strict_types=1);

namespace Modules\People\Domain\DataObjects;

final readonly class ClearExitChecklistItemData
{
    public function __construct(
        public int $checklistId,
        public string $itemCode,
        public int $clearedByUserId,
    ) {}
}
