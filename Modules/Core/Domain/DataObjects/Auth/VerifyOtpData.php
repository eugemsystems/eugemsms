<?php

declare(strict_types=1);

namespace Modules\Core\Domain\DataObjects\Auth;

final readonly class VerifyOtpData
{
    public function __construct(
        public string $phone,
        public string $code,
        public DeviceData $device,
        public ?int $tenantId = null,
        public ?string $ip = null,
    ) {}
}
