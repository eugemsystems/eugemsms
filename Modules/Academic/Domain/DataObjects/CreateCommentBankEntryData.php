<?php

declare(strict_types=1);

namespace Modules\Academic\Domain\DataObjects;

final readonly class CreateCommentBankEntryData
{
    public function __construct(
        public int $schoolId,
        public string $scope,
        public string $text,
        public int $createdByUserId,
        public ?int $subjectId = null,
        public ?string $gradeBand = null,
    ) {}
}
