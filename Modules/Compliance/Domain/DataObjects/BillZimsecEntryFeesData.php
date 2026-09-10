<?php

declare(strict_types=1);

namespace Modules\Compliance\Domain\DataObjects;

final readonly class BillZimsecEntryFeesData
{
    public function __construct(
        public int $registrationId,
        public int $feeComponentId,
        public int $academicYearId,
        public int $termId,
        public int $raisedByUserId,
        public ?int $approvedByUserId = null,
    ) {}
}
