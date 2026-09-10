<?php

declare(strict_types=1);

namespace Modules\Comms\Domain\Actions;

use Illuminate\Support\Carbon;
use Modules\Comms\Domain\DataObjects\RegisterPortalDeviceData;
use Modules\Comms\Domain\Events\DeviceRegistered;
use Modules\Comms\Models\PortalDevice;
use Modules\Core\Domain\Actions\Action;

/**
 * ACT-RegisterPortalDevice (Book I COM-03 §3/BR-COM-03-005). A push
 * token refresh on the SAME `(user_id, device_id)` pair updates the
 * existing row in place — a device re-registering is not a new device.
 */
final class RegisterPortalDeviceAction extends Action
{
    public function execute(RegisterPortalDeviceData $data): PortalDevice
    {
        return $this->transaction(function () use ($data): PortalDevice {
            $device = PortalDevice::updateOrCreate(
                ['user_id' => $data->userId, 'device_id' => $data->deviceId],
                [
                    'platform' => $data->platform,
                    'push_token' => $data->pushToken,
                    'push_token_updated_at' => $data->pushToken !== null ? Carbon::now() : null,
                    'app_version' => $data->appVersion,
                    'os_version' => $data->osVersion,
                    'last_active_at' => Carbon::now(),
                    'is_active' => true,
                    'revoked_at' => null,
                ],
            );

            event(new DeviceRegistered($device));

            return $device;
        });
    }
}
