<?php

declare(strict_types=1);

namespace Modules\Intelligence\Domain\Actions;

use Illuminate\Support\Carbon;
use Modules\Core\Domain\Actions\Action;
use Modules\Intelligence\Models\HardwareDevice;

final class RecordHardwareHeartbeatAction extends Action
{
    public function execute(string $deviceUlid): HardwareDevice
    {
        $device = HardwareDevice::where('ulid', $deviceUlid)->firstOrFail();

        return $this->transaction(function () use ($device): HardwareDevice {
            $device->update(['last_heartbeat_at' => Carbon::now(), 'status' => 'online']);

            return $device->fresh();
        });
    }
}
