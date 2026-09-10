<?php

declare(strict_types=1);

namespace Modules\Facilities\Domain\DataObjects;

final readonly class ConfirmBookingData
{
    public function __construct(
        public int $academicYearId,
        public int $confirmedByUserId,
        public ?int $contractFileId = null,
    ) {}
}
