<?php

declare(strict_types=1);

namespace Modules\Stores\Domain\DataObjects;

use Carbon\CarbonInterface;

final readonly class DisposeAssetData
{
    public function __construct(
        public int $assetId,
        public int $academicYearId,
        public int $termId,
        public CarbonInterface $disposalDate,
        public string $disposalMethod,
        public int $proceedsMinor,
        public string $reason,
        public int $performedByUserId,
        public ?int $proceedsAccountId = null,
        public ?string $buyer = null,
        public ?int $approvedByUserId = null,
    ) {}
}
