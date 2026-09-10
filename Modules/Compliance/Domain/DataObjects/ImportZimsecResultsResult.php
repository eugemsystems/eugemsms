<?php

declare(strict_types=1);

namespace Modules\Compliance\Domain\DataObjects;

use Illuminate\Support\Collection;
use Modules\Compliance\Models\ZimsecResult;

final readonly class ImportZimsecResultsResult
{
    /**
     * @param  Collection<int, ZimsecResult>  $imported
     * @param  array<int, array{candidate_number: string, subject_code: string, reason: string}>  $unmatched
     */
    public function __construct(
        public Collection $imported,
        public array $unmatched,
    ) {}
}
