<?php

declare(strict_types=1);

namespace Modules\People\Domain\DataObjects;

final readonly class OptBackInAlumniContactData
{
    public function __construct(
        public int $alumnusId,
    ) {}
}
