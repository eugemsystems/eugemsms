<?php

declare(strict_types=1);

namespace Modules\Comms\Domain\DataObjects;

final readonly class RegisterSenderIdData
{
    public function __construct(
        public int $schoolId,
        public int $gatewayId,
        public string $senderId,
        public ?string $network = null,
        public ?string $registrationReference = null,
    ) {}
}
