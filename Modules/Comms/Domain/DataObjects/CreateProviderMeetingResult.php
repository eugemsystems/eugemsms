<?php

declare(strict_types=1);

namespace Modules\Comms\Domain\DataObjects;

final readonly class CreateProviderMeetingResult
{
    public function __construct(
        public string $providerMeetingId,
        public string $joinUrl,
        public string $hostUrl,
        public string $passcode,
    ) {}
}
