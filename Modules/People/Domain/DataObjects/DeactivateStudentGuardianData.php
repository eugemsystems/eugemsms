<?php

declare(strict_types=1);

namespace Modules\People\Domain\DataObjects;

final readonly class DeactivateStudentGuardianData
{
    public function __construct(
        public int $studentGuardianId,
        public int $deactivatedByUserId,
    ) {}
}
