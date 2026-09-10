<?php

declare(strict_types=1);

namespace Modules\Reporting\Domain\DataObjects;

final readonly class GenerateClosePackData
{
    public function __construct(
        public int $checklistId,
        public int $generatedByUserId,
    ) {}
}
