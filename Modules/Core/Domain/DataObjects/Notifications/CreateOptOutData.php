<?php

declare(strict_types=1);

namespace Modules\Core\Domain\DataObjects\Notifications;

final readonly class CreateOptOutData
{
    public function __construct(
        public int $schoolId,
        public string $address,
        public string $channel,
        public ?string $reason = 'user_request',
    ) {}
}
