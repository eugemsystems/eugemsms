<?php

declare(strict_types=1);

namespace Modules\Academic\Domain\DataObjects;

final readonly class PublishTimetableData
{
    public function __construct(
        public int $timetableId,
        public int $publishedByUserId,
    ) {}
}
