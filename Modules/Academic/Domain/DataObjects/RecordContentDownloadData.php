<?php

declare(strict_types=1);

namespace Modules\Academic\Domain\DataObjects;

final readonly class RecordContentDownloadData
{
    public function __construct(
        public int $userId,
        public int $contentItemId,
        public ?string $deviceId = null,
    ) {}
}
