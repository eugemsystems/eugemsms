<?php

declare(strict_types=1);

namespace Modules\Comms\Http\Controllers\Api\V1;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Comms\Domain\Actions\RegisterPortalDeviceAction;
use Modules\Comms\Domain\Actions\RevokePortalDeviceAction;
use Modules\Comms\Domain\DataObjects\RegisterPortalDeviceData;
use Modules\Comms\Models\PortalDevice;
use Modules\Core\Http\Support\ApiResponse;

/**
 * `/api/v1/me/devices` (Volume 1 §9.3). The mobile app registers its push token here, and
 * re-registering the same device id refreshes the token in place. A user can only ever see or
 * remove their own devices.
 */
final class DevicesController
{
    public function store(Request $request, RegisterPortalDeviceAction $register): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $data = $request->validate([
            'device_id' => ['required', 'string', 'max:100'],
            'platform' => ['required', 'in:android,ios,web'],
            'push_token' => ['nullable', 'string', 'max:500'],
            'app_version' => ['nullable', 'string', 'max:30'],
            'os_version' => ['nullable', 'string', 'max:30'],
        ]);

        $device = $register->execute(new RegisterPortalDeviceData($user->id, $data['device_id'], $data['platform'], $data['push_token'] ?? null, $data['app_version'] ?? null, $data['os_version'] ?? null));

        return ApiResponse::ok(['id' => $device->ulid, 'device_id' => $device->device_id, 'platform' => $device->platform], status: 201);
    }

    public function destroy(Request $request, string $device, RevokePortalDeviceAction $revoke): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $found = PortalDevice::query()->where('user_id', $user->id)->where('ulid', $device)->first();
        abort_if($found === null, 404);

        $revoke->execute($found->id);

        return ApiResponse::ok(['revoked' => true]);
    }
}
