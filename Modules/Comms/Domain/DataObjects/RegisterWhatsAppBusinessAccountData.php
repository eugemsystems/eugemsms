<?php

declare(strict_types=1);

namespace Modules\Comms\Domain\DataObjects;

final readonly class RegisterWhatsAppBusinessAccountData
{
    public function __construct(
        public int $schoolId,
        public int $gatewayId,
        public string $wabaId,
        public string $displayPhoneNumber,
        public string $displayName,
    ) {}
}
