<?php

declare(strict_types=1);

namespace Modules\Core\Domain\DataObjects\Install;

final readonly class SuperAdminData
{
    public function __construct(
        public string $name,
        public string $email,
        public string $password,
    ) {}
}
