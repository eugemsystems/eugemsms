<?php

declare(strict_types=1);

namespace Modules\Academic\Domain\DataObjects;

final readonly class ReturnSchemeOfWorkData
{
    public function __construct(
        public int $schemeOfWorkId,
        public int $reviewedByUserId,
        public string $reviewComments,
    ) {}
}
