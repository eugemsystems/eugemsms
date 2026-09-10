<?php

declare(strict_types=1);

namespace Modules\Finance\Domain\DataObjects;

/**
 * Book D ACA-02 §7/BR-ACA-02-016. Backs `POST /selections/preview-fee`
 * — a family's proposed subject set, not yet enrolled anywhere.
 */
final readonly class PreviewIndicativeFeeData
{
    /**
     * @param  array<int, int>  $subjectIds
     */
    public function __construct(
        public int $studentId,
        public int $termId,
        public array $subjectIds,
    ) {}
}
