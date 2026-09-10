<?php

declare(strict_types=1);

namespace Modules\Security\Domain\DataObjects;

use Carbon\CarbonInterface;

final readonly class RecordOccurrenceData
{
    /**
     * @param  array<int, int>|null  $photoFileIds
     */
    public function __construct(
        public int $schoolId,
        public CarbonInterface $occurredAt,
        public string $category,
        public string $description,
        public int $recordedByUserId,
        public ?string $shift = null,
        public ?string $location = null,
        public ?string $personsInvolved = null,
        public ?string $actionTaken = null,
        public ?string $cctvReference = null,
        public ?array $photoFileIds = null,
        public ?int $correctsEntryId = null,
    ) {}
}
