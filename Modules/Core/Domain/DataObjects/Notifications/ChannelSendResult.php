<?php

declare(strict_types=1);

namespace Modules\Core\Domain\DataObjects\Notifications;

final readonly class ChannelSendResult
{
    public function __construct(
        public bool $succeeded,
        public ?string $providerMessageId = null,
        public ?string $errorCode = null,
        public ?string $errorMessage = null,
        public ?int $costMinor = null,
    ) {}
}
