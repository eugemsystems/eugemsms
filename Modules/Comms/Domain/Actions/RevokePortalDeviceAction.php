<?php

declare(strict_types=1);

namespace Modules\Comms\Domain\Actions;

use App\Models\User;
use Illuminate\Support\Carbon;
use Modules\Comms\Domain\Events\DeviceRevoked;
use Modules\Comms\Models\PortalDevice;
use Modules\Core\Domain\Actions\Action;
use Modules\Core\Models\PersonalAccessToken;

/**
 * ACT-RevokePortalDevice (Book I COM-03 §6 ⭐/BR-COM-03-007
 * (AC-COM-03-006)). Immediately invalidates the push token — clearing
 * it, so a stale send is never even attempted — and revokes every
 * `personal_access_tokens` row sharing this device id, the same
 * `device_id` grouping Book A CORE-05's own token issuance already
 * uses. The offline cache clears on NEXT CONTACT (there is no way to
 * reach into an already-offline device) — this action only sets the
 * server-side state a client checks when it next calls in.
 */
final class RevokePortalDeviceAction extends Action
{
    public function execute(int $deviceId): PortalDevice
    {
        return $this->transaction(function () use ($deviceId): PortalDevice {
            $device = PortalDevice::findOrFail($deviceId);

            $device->update([
                'is_active' => false,
                'revoked_at' => Carbon::now(),
                'push_token' => null,
                'push_token_updated_at' => null,
            ]);

            PersonalAccessToken::where('tokenable_type', User::class)
                ->where('tokenable_id', $device->user_id)
                ->where('device_id', $device->device_id)
                ->whereNull('revoked_at')
                ->update(['revoked_at' => Carbon::now()]);

            event(new DeviceRevoked($device));

            return $device;
        });
    }
}
