<?php

declare(strict_types=1);

namespace Modules\People\Domain\DataObjects;

final readonly class CreateGuardianData
{
    public function __construct(
        public int $schoolId,
        public string $guardianType,
        public int $createdByUserId,
        public ?string $title = null,
        public ?string $firstName = null,
        public ?string $lastName = null,
        public ?string $organisationName = null,
        public ?string $organisationType = null,
        public ?string $primaryPhone = null,
        public ?string $email = null,
    ) {}
}
