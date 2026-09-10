<?php

declare(strict_types=1);

namespace Modules\Comms\Domain\DataObjects;

final readonly class RegisterForEventData
{
    public function __construct(
        public int $schoolId,
        public int $registrationId,
        public string $attendeeType,
        public string $attendeeName,
        public ?int $guardianId = null,
        public ?int $studentId = null,
        public int $partySize = 1,
        public ?int $raisedByUserId = null,
        public ?string $feeCurrency = null,
    ) {}
}
