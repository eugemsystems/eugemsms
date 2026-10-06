<?php

declare(strict_types=1);

namespace Modules\People\Domain\DataObjects;

final readonly class ChangeEntranceExamStatusData
{
    public function __construct(
        public int $examId,
        public string $newStatus,
    ) {}
}
