<?php

declare(strict_types=1);

namespace Modules\Core\Domain\DataObjects\Auth;

final readonly class RequestOtpData
{
    public function __construct(
        public string $phone,
        public ?int $tenantId = null,
    ) {}
}
