<?php

declare(strict_types=1);

namespace Modules\Compliance\Domain\DataObjects;

use Carbon\CarbonInterface;

final readonly class GenerateStatutorySchoolReturnData
{
    public function __construct(
        public int $schoolId,
        public string $returnType,
        public string $periodReference,
        public CarbonInterface $dueDate,
        public string $authority,
        public int $generatedByUserId,
    ) {}
}
