<?php

declare(strict_types=1);

namespace Modules\People\Domain\DataObjects;

final readonly class RecordInterviewOutcomeData
{
    /**
     * @param  array<string, float|int>  $scores
     */
    public function __construct(
        public int $interviewId,
        public bool $attended,
        public array $scores = [],
        public ?string $recommendation = null,
        public ?string $panelNotes = null,
    ) {}
}
