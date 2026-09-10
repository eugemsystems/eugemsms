<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Contracts\Notifications;

use Modules\Core\Domain\DataObjects\Notifications\ChannelSendResult;

/**
 * Book A CORE-09 §1/BR-CORE-09-001. Gateway-specific implementations
 * (Twilio, WhatsApp Business API, an email provider, ...) belong to
 * COM-01, not built yet — `NullNotificationChannelDriver` is the
 * default binding until then, same deferred-dependency shape as every
 * other Null* provider in this codebase.
 */
interface NotificationChannelDriver
{
    public function channel(): string;

    public function send(string $address, ?string $subject, string $body): ChannelSendResult;
}
