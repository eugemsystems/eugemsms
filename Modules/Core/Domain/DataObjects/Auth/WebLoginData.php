<?php

declare(strict_types=1);

namespace Modules\Core\Domain\DataObjects\Auth;

final readonly class WebLoginData
{
    public function __construct(
        public string $identifier,
        public string $password,
        public ?int $tenantId = null,
        public ?string $ip = null,
        public ?string $userAgent = null,
    ) {}
}
