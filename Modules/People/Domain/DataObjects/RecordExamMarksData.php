<?php

declare(strict_types=1);

namespace Modules\People\Domain\DataObjects;

final readonly class RecordExamMarksData
{
    /**
     * @param  array<string, float|int>  $marks
     */
    public function __construct(
        public int $candidateId,
        public bool $attended,
        public array $marks = [],
    ) {}
}
