<?php

declare(strict_types=1);

namespace Modules\Academic\Domain\DataObjects;

final readonly class CreateAttendanceReasonCodeData
{
    public function __construct(
        public int $schoolId,
        public string $code,
        public string $name,
        public bool $countsAsPresent = false,
        public bool $countsTowardPercentage = true,
        public bool $isAuthorised = true,
        public bool $requiresDocument = false,
        public bool $suppressesNotification = false,
        public bool $triggersWelfareFlag = false,
    ) {}
}
