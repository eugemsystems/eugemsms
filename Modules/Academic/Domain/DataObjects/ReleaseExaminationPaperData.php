<?php

declare(strict_types=1);

namespace Modules\Academic\Domain\DataObjects;

final readonly class ReleaseExaminationPaperData
{
    public function __construct(
        public int $paperId,
        public int $requestedByUserId,
        public ?string $ip = null,
    ) {}
}
