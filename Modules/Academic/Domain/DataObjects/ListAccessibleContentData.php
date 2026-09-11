<?php

declare(strict_types=1);

namespace Modules\Academic\Domain\DataObjects;

final readonly class ListAccessibleContentData
{
    public function __construct(
        public int $courseSpaceId,
        public int $studentId,
    ) {}
}
