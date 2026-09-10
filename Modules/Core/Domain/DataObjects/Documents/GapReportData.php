<?php

declare(strict_types=1);

namespace Modules\Core\Domain\DataObjects\Documents;

final readonly class GapReportData
{
    public function __construct(
        public int $schoolId,
        public ?int $seriesId = null,
    ) {}
}
