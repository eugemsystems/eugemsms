<?php

declare(strict_types=1);

namespace Modules\Core\Domain\DataObjects\Schools;

final readonly class SwitchSchoolData
{
    public function __construct(
        public int $userId,
        public int $schoolId,
    ) {}
}
