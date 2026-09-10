<?php

declare(strict_types=1);

namespace Modules\Comms\Domain\DataObjects;

use Carbon\CarbonInterface;

final readonly class PostNoticeData
{
    /**
     * @param  array<int, int>|null  $attachmentFileIds
     */
    public function __construct(
        public int $schoolId,
        public string $title,
        public string $body,
        public string $audienceScope,
        public int $postedByUserId,
        public string $priority = 'normal',
        public ?int $audienceScopeId = null,
        public bool $isPinned = false,
        public ?CarbonInterface $publishAt = null,
        public ?CarbonInterface $expiresAt = null,
        public ?array $attachmentFileIds = null,
    ) {}
}
