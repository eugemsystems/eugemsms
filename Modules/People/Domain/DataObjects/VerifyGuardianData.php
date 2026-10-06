<?php

declare(strict_types=1);

namespace Modules\People\Domain\DataObjects;

final readonly class VerifyGuardianData
{
    public function __construct(
        public int $verificationId,
        public int $verifiedByUserId,
    ) {}
}
