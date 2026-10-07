<?php

declare(strict_types=1);

namespace Modules\Academic\Domain\DataObjects;

final readonly class ModerateAssessmentData
{
    public function __construct(
        public int $assessmentId,
        public int $moderatedByUserId,
        public ?string $moderationNote = null,
    ) {}
}
