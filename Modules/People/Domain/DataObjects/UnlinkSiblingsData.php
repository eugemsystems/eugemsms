<?php

declare(strict_types=1);

namespace Modules\People\Domain\DataObjects;

final readonly class UnlinkSiblingsData
{
    public function __construct(
        public int $studentId,
        public int $siblingStudentId,
    ) {}
}
