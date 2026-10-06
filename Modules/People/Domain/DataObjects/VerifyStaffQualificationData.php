<?php

declare(strict_types=1);

namespace Modules\People\Domain\DataObjects;

final readonly class VerifyStaffQualificationData
{
    public function __construct(
        public int $qualificationId,
        public int $verifiedByUserId,
    ) {}
}
