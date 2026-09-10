<?php

declare(strict_types=1);

namespace Modules\Intelligence\Domain\Actions;

use Illuminate\Support\Carbon;
use Modules\Core\Domain\Actions\Action;
use Modules\Core\Domain\Support\Settings\ScopeChain;
use Modules\Core\Domain\Support\Settings\SettingResolver;
use Modules\Intelligence\Domain\Events\HardwareDeviceOffline;
use Modules\Intelligence\Models\HardwareDevice;

/**
 * ACT-MarkOfflineHardwareDevices (Book J INT-04 §2/BR-INT-04-009
 * (AC-INT-04-004)). Meant to run on a schedule, the same "the real
 * mechanism exists, wiring the actual cron entry is deployment work"
 * boundary every other nightly/periodic action in this book set
 * draws. Marking a device offline never touches the owning module's
 * manual-entry path — that path was never gated on the device's
 * status in the first place (BR-INT-04-009's second half is a
 * property of `MarkRollCallAction`/`RecordCheckpointMovementAction`
 * themselves, not of this action).
 */
final class MarkOfflineHardwareDevicesAction extends Action
{
    public function __construct(
        private readonly SettingResolver $settings,
    ) {}

    /**
     * @return array<int, HardwareDevice>
     */
    public function execute(int $schoolId): array
    {
        $windowMinutes = (int) $this->settings->get('integration.hardware_heartbeat_window_minutes', new ScopeChain(schoolId: $schoolId));
        $cutoff = Carbon::now()->subMinutes($windowMinutes);

        $silent = HardwareDevice::where('school_id', $schoolId)
            ->where('status', '!=', 'offline')
            ->where(fn ($q) => $q->whereNull('last_heartbeat_at')->orWhere('last_heartbeat_at', '<', $cutoff))
            ->get();

        return $silent->map(function (HardwareDevice $device): HardwareDevice {
            $updated = $this->transaction(function () use ($device): HardwareDevice {
                $device->update(['status' => 'offline']);

                return $device->fresh();
            });

            event(new HardwareDeviceOffline($updated));

            return $updated;
        })->all();
    }
}
