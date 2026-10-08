<?php

declare(strict_types=1);

namespace Modules\Academic\Domain\DataObjects;

final readonly class UploadExaminationPaperFileData
{
    public function __construct(
        public int $paperId,
        public string $fileType,
        public string $contents,
        public string $originalName,
        public int $uploadedByUserId,
    ) {}
}
