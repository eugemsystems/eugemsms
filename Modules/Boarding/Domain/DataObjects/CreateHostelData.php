<?php

declare(strict_types=1);

namespace Modules\Boarding\Domain\DataObjects;

final readonly class CreateHostelData
{
    public function __construct(
        public int $schoolId,
        public string $code,
        public string $name,
        public string $gender,
        public int $createdBy,
        public ?int $sectionId = null,
        public ?int $houseId = null,
        public ?int $housemasterStaffId = null,
        public ?int $matronStaffId = null,
        public ?int $deputyStaffId = null,
        public ?string $building = null,
        public bool $hasSickBay = false,
        public bool $hasPrepRoom = false,
    ) {}
}
