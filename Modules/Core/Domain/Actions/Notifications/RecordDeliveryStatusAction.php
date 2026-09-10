<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Actions\Notifications;

use Illuminate\Support\Carbon;
use Modules\Core\Domain\Actions\Action;
use Modules\Core\Domain\DataObjects\Notifications\RecordDeliveryStatusData;
use Modules\Core\Models\Notification;
use Modules\Core\Models\NotificationOptOut;

/**
 * ACT-RecordDeliveryStatus (Book A CORE-09 BR-CORE-09-010/011). Called
 * from a provider webhook (COM-01, not built yet). A hard bounce also
 * auto-adds the address to the opt-out list with reason `invalid` —
 * the caller doesn't need to remember to do that separately.
 */
final class RecordDeliveryStatusAction extends Action
{
    public function execute(RecordDeliveryStatusData $data): Notification
    {
        $notification = Notification::query()->findOrFail($data->notificationId);

        return $this->transaction(function () use ($notification, $data): Notification {
            $timestampColumn = match ($data->status) {
                'delivered' => 'delivered_at',
                'read' => 'read_at',
                'failed', 'bounced' => 'failed_at',
                default => null,
            };

            $notification->forceFill(array_filter([
                'status' => $data->status,
                'error_code' => $data->errorCode,
                'error_message' => $data->errorMessage,
                $timestampColumn => $timestampColumn !== null ? Carbon::now() : null,
            ], fn ($value): bool => $value !== null))->save();

            if ($data->isHardBounce) {
                NotificationOptOut::firstOrCreate(
                    ['school_id' => $notification->school_id, 'address' => $notification->recipient_address, 'channel' => $notification->channel],
                    ['reason' => 'invalid', 'opted_out_at' => Carbon::now()],
                );
            }

            return $notification;
        });
    }
}
