<?php

declare(strict_types=1);

namespace Modules\Boarding\Domain\DataObjects;

final readonly class RecordDepartureData
{
    public function __construct(
        public int $exeatId,
        public CollectionClaim $claim,
        public int $gateStaffUserId,
        public bool $requiresPhotoId = true,
    ) {}
}
