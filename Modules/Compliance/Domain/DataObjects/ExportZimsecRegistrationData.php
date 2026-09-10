<?php

declare(strict_types=1);

namespace Modules\Compliance\Domain\DataObjects;

final readonly class ExportZimsecRegistrationData
{
    public function __construct(
        public int $registrationId,
        public int $exportedByUserId,
        public bool $acknowledgeWarnings = false,
    ) {}
}
