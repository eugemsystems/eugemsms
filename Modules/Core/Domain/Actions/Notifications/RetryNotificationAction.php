<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Actions\Notifications;

use Illuminate\Support\Carbon;
use Modules\Core\Domain\Actions\Action;
use Modules\Core\Domain\DataObjects\Notifications\RetryNotificationData;
use Modules\Core\Domain\Exceptions\DomainException;
use Modules\Core\Domain\Registry\NotificationChannelDriverRegistry;
use Modules\Core\Models\Notification;

/**
 * ACT-RetryNotification (Book A CORE-09 §5's "Failure report ... with
 * bulk retry"). Re-attempts the send on the notification's own already-
 * resolved channel/address/body — it does not re-run
 * `DispatchNotificationAction`'s full resolution pipeline (opt-out,
 * preference, dedupe, budget), since those decisions already passed
 * once to get this notification to a real channel-send attempt in the
 * first place; only `failed` notifications are retryable, not
 * `suppressed` ones, which were deliberately never sent per those
 * business rules and would need the original dispatch context (recipient,
 * audience) to be re-evaluated properly, not just resent.
 */
final class RetryNotificationAction extends Action
{
    public function execute(RetryNotificationData $data): Notification
    {
        $notification = Notification::query()->findOrFail($data->notificationId);

        if ($notification->status !== 'failed') {
            throw new class("Only a failed notification can be retried; this one is [{$notification->status}].") extends DomainException
            {
                public function errorCode(): string
                {
                    return 'NOTIFICATION_NOT_FAILED';
                }
            };
        }

        return $this->transaction(function () use ($notification): Notification {
            $driver = NotificationChannelDriverRegistry::forChannel($notification->channel);
            $result = $driver->send($notification->recipient_address, $notification->subject, $notification->body);

            $notification->forceFill([
                'attempt_count' => $notification->attempt_count + 1,
                'provider' => $notification->channel,
                'provider_message_id' => $result->providerMessageId,
                'error_code' => $result->errorCode,
                'error_message' => $result->errorMessage,
                'cost_minor' => $result->costMinor ?? $notification->cost_minor,
                'status' => $result->succeeded ? 'sent' : 'failed',
                'sent_at' => $result->succeeded ? Carbon::now() : $notification->sent_at,
                'failed_at' => $result->succeeded ? null : Carbon::now(),
            ])->save();

            return $notification;
        });
    }
}
