<?php

declare(strict_types=1);

namespace Modules\Academic\Domain\DataObjects;

final readonly class ReviewLessonPlanData
{
    public function __construct(
        public int $lessonPlanId,
        public ?string $hodComments = null,
    ) {}
}
