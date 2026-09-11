<?php

declare(strict_types=1);

namespace Modules\Academic\Domain\DataObjects;

final readonly class PostDiscussionMessageData
{
    public function __construct(
        public int $threadId,
        public string $postedByType,
        public int $postedById,
        public string $content,
    ) {}
}
