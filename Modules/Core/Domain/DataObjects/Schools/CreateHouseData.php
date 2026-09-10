<?php

declare(strict_types=1);

namespace Modules\Core\Domain\DataObjects\Schools;

final readonly class CreateHouseData
{
    public function __construct(
        public int $schoolId,
        public string $code,
        public string $name,
        public ?string $colour = null,
        public ?string $motto = null,
        public ?int $housemasterId = null,
    ) {}
}
