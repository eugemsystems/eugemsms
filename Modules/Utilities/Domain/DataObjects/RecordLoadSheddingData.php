<?php

declare(strict_types=1);

namespace Modules\Utilities\Domain\DataObjects;

use Carbon\CarbonInterface;

final readonly class RecordLoadSheddingData
{
    public function __construct(
        public int $schoolId,
        public CarbonInterface $scheduleDate,
        public string $startsAt,
        public string $endsAt,
        public string $source,
        public ?string $stage = null,
        public ?CarbonInterface $actualOutageStart = null,
        public ?CarbonInterface $actualOutageEnd = null,
        public ?string $impactNote = null,
    ) {}
}
