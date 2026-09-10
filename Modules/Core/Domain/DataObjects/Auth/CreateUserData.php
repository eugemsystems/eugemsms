<?php

declare(strict_types=1);

namespace Modules\Core\Domain\DataObjects\Auth;

use Modules\Core\Domain\Support\Auth\UserType;

final readonly class CreateUserData
{
    public function __construct(
        public string $firstName,
        public string $lastName,
        public ?string $otherNames = null,
        public ?string $email = null,
        public ?string $phone = null,
        public ?string $username = null,
        public ?string $password = null,
        public UserType $userType = UserType::Staff,
        public ?int $tenantId = null,
        public string $locale = 'en_ZW',
        public ?int $createdByUserId = null,
    ) {}
}
