<?php

declare(strict_types=1);

namespace Modules\Compliance\Domain\DataObjects;

use Modules\Compliance\Models\StatutorySchoolReturn;

final readonly class GenerateStatutorySchoolReturnResult
{
    /**
     * @param  array<string, mixed>|null  $divergence  present only when the return already existed — the difference between its frozen data_snapshot and what live data looks like now (AC-CMP-02-002)
     */
    public function __construct(
        public StatutorySchoolReturn $return,
        public bool $wasAlreadyGenerated,
        public ?array $divergence = null,
    ) {}
}
