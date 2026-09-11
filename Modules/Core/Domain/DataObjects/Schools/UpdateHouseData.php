<?php

declare(strict_types=1);

namespace Modules\Core\Domain\DataObjects\Schools;

final readonly class UpdateHouseData
{
    public function __construct(
        public int $schoolId,
        public int $houseId,
        public string $code,
        public string $name,
        public ?string $colour = null,
        public ?string $motto = null,
    ) {}
}
