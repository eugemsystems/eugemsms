<?php

declare(strict_types=1);

namespace Modules\Academic\Domain\DataObjects;

final readonly class CreateContentItemData
{
    public function __construct(
        public int $courseSpaceId,
        public string $contentType,
        public string $title,
        public ?int $fileId = null,
        public ?string $externalUrl = null,
        public ?int $fileSizeBytes = null,
        public bool $isDownloadableOffline = true,
        public ?int $sortOrder = null,
    ) {}
}
