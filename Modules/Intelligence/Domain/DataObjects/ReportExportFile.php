<?php

declare(strict_types=1);

namespace Modules\Intelligence\Domain\DataObjects;

final readonly class ReportExportFile
{
    public function __construct(
        public string $content,
        public string $mimeType,
        public string $filename,
    ) {}
}
