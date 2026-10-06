<?php

declare(strict_types=1);

namespace Modules\Academic\Domain\DataObjects;

final readonly class PublishReportCardsResult
{
    /**
     * @param  array<int, int>  $stillWithheldStudentIds
     */
    public function __construct(
        public int $published,
        public int $releasedFromWithheld,
        public int $stillWithheld,
        public int $notGenerated,
        public array $stillWithheldStudentIds = [],
    ) {}
}
