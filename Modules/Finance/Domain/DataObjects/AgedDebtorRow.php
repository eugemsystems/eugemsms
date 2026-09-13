<?php

declare(strict_types=1);

namespace Modules\Finance\Domain\DataObjects;

final readonly class AgedDebtorRow
{
    /**
     * @param  array<string, int>  $bucketMinor  bucket label => minor amount
     */
    public function __construct(
        public int $studentId,
        public string $admissionNumber,
        public string $studentName,
        public array $bucketMinor,
        public int $totalMinor,
    ) {}
}
