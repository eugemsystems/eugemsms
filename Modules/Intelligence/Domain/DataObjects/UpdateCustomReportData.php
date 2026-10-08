<?php

declare(strict_types=1);

namespace Modules\Intelligence\Domain\DataObjects;

final readonly class UpdateCustomReportData
{
    public function __construct(
        public int $reportId,
        public string $name,
        public ?string $description = null,
        public ?string $chartType = null,
    ) {}
}
