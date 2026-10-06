<?php

declare(strict_types=1);

namespace Modules\Academic\Domain\DataObjects;

final readonly class PublishReportCardsData
{
    public function __construct(
        public int $schoolId,
        public int $termId,
        public int $publishedByUserId,
        public ?int $classId = null,
        public ?int $gradeLevelId = null,
    ) {}
}
