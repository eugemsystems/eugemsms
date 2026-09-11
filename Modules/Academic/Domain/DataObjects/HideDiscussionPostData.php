<?php

declare(strict_types=1);

namespace Modules\Academic\Domain\DataObjects;

final readonly class HideDiscussionPostData
{
    public function __construct(
        public int $postId,
        public int $hiddenByUserId,
        public string $reason,
    ) {}
}
