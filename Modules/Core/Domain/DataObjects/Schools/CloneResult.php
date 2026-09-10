<?php

declare(strict_types=1);

namespace Modules\Core\Domain\DataObjects\Schools;

final readonly class CloneResult
{
    public function __construct(
        public int $sectionsCreated,
        public int $gradeLevelsCreated,
        public int $housesCreated,
    ) {}
}
