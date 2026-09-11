<?php

declare(strict_types=1);

namespace Modules\Core\Domain\DataObjects\Schools;

final readonly class DeleteHouseData
{
    public function __construct(
        public int $schoolId,
        public int $houseId,
    ) {}
}
