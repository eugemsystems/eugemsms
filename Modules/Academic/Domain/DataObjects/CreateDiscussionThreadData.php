<?php

declare(strict_types=1);

namespace Modules\Academic\Domain\DataObjects;

final readonly class CreateDiscussionThreadData
{
    public function __construct(
        public int $courseSpaceId,
        public string $title,
        public int $createdByUserId,
    ) {}
}
