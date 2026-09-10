<?php

declare(strict_types=1);

namespace Modules\Academic\Domain\DataObjects;

final readonly class PublishExaminationResultsData
{
    public function __construct(
        public int $sessionId,
        public int $publishedByUserId,
    ) {}
}
