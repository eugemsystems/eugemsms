<?php

declare(strict_types=1);

namespace Modules\People\Domain\DataObjects;

final readonly class CreateEstablishmentPostData
{
    public function __construct(
        public int $schoolId,
        public string $title,
        public ?int $departmentId = null,
        public ?string $grade = null,
        public int $approvedCount = 1,
        public bool $isTeaching = false,
    ) {}
}
