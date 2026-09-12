<?php

declare(strict_types=1);

namespace Modules\Core\Domain\DataObjects\Auth;

use Modules\Core\Domain\Support\Auth\UserType;

final readonly class UpdateUserData
{
    public function __construct(
        public int $userId,
        public string $firstName,
        public string $lastName,
        public ?string $otherNames = null,
        public ?string $email = null,
        public ?string $phone = null,
        public ?string $username = null,
        public UserType $userType = UserType::Staff,
        public string $locale = 'en_ZW',
        public ?int $updatedByUserId = null,
    ) {}
}
