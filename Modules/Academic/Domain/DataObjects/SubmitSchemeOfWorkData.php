<?php

declare(strict_types=1);

namespace Modules\Academic\Domain\DataObjects;

final readonly class SubmitSchemeOfWorkData
{
    public function __construct(
        public int $schemeOfWorkId,
    ) {}
}
