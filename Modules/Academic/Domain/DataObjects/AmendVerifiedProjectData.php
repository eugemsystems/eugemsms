<?php

declare(strict_types=1);

namespace Modules\Academic\Domain\DataObjects;

final readonly class AmendVerifiedProjectData
{
    /**
     * @param  array<int, CriterionMarkInput>  $criterionMarks
     */
    public function __construct(
        public int $learnerProjectId,
        public int $changedByUserId,
        public string $changeReason,
        public array $criterionMarks,
        public bool $approved = false,
    ) {}
}
