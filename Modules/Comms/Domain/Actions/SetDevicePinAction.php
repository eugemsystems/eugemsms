<?php

declare(strict_types=1);

namespace Modules\Comms\Domain\Actions;

use Illuminate\Validation\ValidationException;
use Modules\Comms\Models\PortalDevice;
use Modules\Core\Domain\Actions\Action;

/**
 * ACT-SetDevicePin (Book I COM-03 §6/BR-COM-03-006). The app-level PIN
 * is stored separately from the account's own credentials —
 * `PortalDevice.app_pin_hash`, never touching `App\Models\User`'s own
 * password at all. Losing the phone doesn't expose the account if the
 * app itself is locked, even with the underlying session token still
 * technically valid — this Action is what sets that independent lock,
 * not what checks it (the check is an app-layer concern on open).
 */
final class SetDevicePinAction extends Action
{
    public function execute(int $deviceId, ?string $pin, int $minLength): PortalDevice
    {
        if ($pin !== null && strlen($pin) < $minLength) {
            throw ValidationException::withMessages(['pin' => "The PIN must be at least {$minLength} characters."]);
        }

        return $this->transaction(function () use ($deviceId, $pin): PortalDevice {
            $device = PortalDevice::findOrFail($deviceId);
            $device->update(['app_pin_hash' => $pin]);

            return $device;
        });
    }
}
