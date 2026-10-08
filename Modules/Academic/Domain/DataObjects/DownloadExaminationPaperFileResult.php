<?php

declare(strict_types=1);

namespace Modules\Academic\Domain\DataObjects;

final readonly class DownloadExaminationPaperFileResult
{
    public function __construct(
        public string $contents,
        public string $filename,
        public string $mimeType,
    ) {}
}
