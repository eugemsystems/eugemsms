<?php

declare(strict_types=1);

namespace Modules\Core\Domain\DataObjects\Files;

use Carbon\CarbonInterface;

final readonly class UploadFileData
{
    public function __construct(
        public int $schoolId,
        public string $category,
        public string $contents,
        public string $originalName,
        public int $uploadedByUserId,
        public ?string $attachableType = null,
        public ?int $attachableId = null,
        public ?CarbonInterface $expiresOn = null,
    ) {}
}
