<?php

declare(strict_types=1);

namespace Modules\Security\Domain\DataObjects;

use Carbon\CarbonInterface;

final readonly class ReportLostPropertyData
{
    public function __construct(
        public int $schoolId,
        public CarbonInterface $foundOn,
        public string $description,
        public ?string $foundLocation = null,
        public ?int $foundByUserId = null,
        public ?int $photoFileId = null,
    ) {}
}
