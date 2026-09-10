<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Support\Notifications;

use Illuminate\Support\Facades\Log;
use Modules\Core\Domain\Contracts\Notifications\NotificationChannelDriver;
use Modules\Core\Domain\DataObjects\Notifications\ChannelSendResult;

final readonly class NullNotificationChannelDriver implements NotificationChannelDriver
{
    public function __construct(
        private string $channelName,
    ) {}

    public function channel(): string
    {
        return $this->channelName;
    }

    public function send(string $address, ?string $subject, string $body): ChannelSendResult
    {
        Log::info("[{$this->channelName}] notification (no gateway configured yet)", ['to' => $address, 'subject' => $subject]);

        return new ChannelSendResult(succeeded: true, providerMessageId: 'null-'.uniqid());
    }
}
