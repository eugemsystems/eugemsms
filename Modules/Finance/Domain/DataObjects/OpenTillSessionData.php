<?php

declare(strict_types=1);

namespace Modules\Finance\Domain\DataObjects;

final readonly class OpenTillSessionData
{
    /**
     * @param  array<string, int>  $openingFloat
     */
    public function __construct(
        public int $schoolId,
        public int $academicYearId,
        public int $termId,
        public int $tillId,
        public int $cashierId,
        public array $openingFloat,
    ) {}
}
