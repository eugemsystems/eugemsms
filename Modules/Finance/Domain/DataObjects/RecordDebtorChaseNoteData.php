<?php

declare(strict_types=1);

namespace Modules\Finance\Domain\DataObjects;

final readonly class RecordDebtorChaseNoteData
{
    public function __construct(
        public int $schoolId,
        public int $studentId,
        public string $outcome,
        public string $note,
        public int $recordedByUserId,
        public ?string $nextActionOn = null,
    ) {}
}
