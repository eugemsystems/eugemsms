<?php

declare(strict_types=1);

namespace Modules\Comms\Domain\DataObjects;

final readonly class RegisterMessageGatewayData
{
    public function __construct(
        public int $schoolId,
        public string $channel,
        public string $driver,
        public string $name,
        public string $credentials,
        public int $createdByUserId,
        public ?string $webhookSecret = null,
        public bool $isDefault = false,
        public bool $isSandbox = false,
        public int $priority = 0,
    ) {}
}
