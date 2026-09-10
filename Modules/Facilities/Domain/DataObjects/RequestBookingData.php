<?php

declare(strict_types=1);

namespace Modules\Facilities\Domain\DataObjects;

use Carbon\CarbonInterface;

final readonly class RequestBookingData
{
    public function __construct(
        public int $schoolId,
        public int $termId,
        public int $resourceId,
        public string $bookingType,
        public string $purpose,
        public CarbonInterface $startsAt,
        public CarbonInterface $endsAt,
        public int $requestedByUserId,
        public ?int $expectedAttendance = null,
        public ?int $requestedByStaffId = null,
        public ?int $departmentId = null,
        public ?string $hirerName = null,
        public ?string $hirerContact = null,
        public ?string $hirerOrganisation = null,
        public ?int $hireAmountMinor = null,
        public ?int $depositAmountMinor = null,
        public ?string $recurrenceRule = null,
        public ?int $parentBookingId = null,
    ) {}
}
