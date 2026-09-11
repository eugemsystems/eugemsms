<?php

declare(strict_types=1);

namespace Modules\People\Domain\DataObjects;

final readonly class VerifyCareerUpdateData
{
    public function __construct(
        public int $careerUpdateId,
    ) {}
}
