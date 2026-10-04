<?php

declare(strict_types=1);

namespace Modules\Boarding\Domain\DataObjects;

final readonly class CreateHostelWingData
{
    public function __construct(
        public int $schoolId,
        public int $hostelId,
        public string $code,
        public string $name,
        public ?string $floor = null,
        public ?int $supervisorStaffId = null,
        public ?int $prefectStudentId = null,
    ) {}
}
