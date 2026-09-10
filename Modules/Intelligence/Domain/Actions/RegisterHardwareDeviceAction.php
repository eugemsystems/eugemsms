<?php

declare(strict_types=1);

namespace Modules\Intelligence\Domain\Actions;

use InvalidArgumentException;
use Modules\Core\Domain\Actions\Action;
use Modules\Intelligence\Domain\Registry\HardwareScanRouteRegistry;
use Modules\Intelligence\Models\HardwareDevice;

/**
 * ACT-RegisterHardwareDevice (Book J INT-04 §3 ⭐/BR-INT-04-007). Every
 * device gets its OWN `ApiClient` credential, scoped to exactly
 * `{purpose}:write` — never a broader ability, and never a human
 * user's own credential reused for a device.
 */
final class RegisterHardwareDeviceAction extends Action
{
    public function __construct(
        private readonly IssueApiClientAction $issueApiClient,
    ) {}

    /**
     * @return array{device: HardwareDevice, plaintextKey: string}
     */
    public function execute(int $schoolId, string $deviceType, string $purpose, ?string $location = null, ?int $createdByUserId = null): array
    {
        if (HardwareScanRouteRegistry::get($purpose) === null) {
            throw new InvalidArgumentException("Purpose '{$purpose}' has no registered scan route to authorise a device for.");
        }

        $issued = $this->issueApiClient->execute(
            schoolId: $schoolId,
            name: "Hardware device ({$deviceType}, {$purpose})",
            clientType: 'hardware_device',
            scopedAbilities: ["{$purpose}:write"],
            createdByUserId: $createdByUserId,
        );

        $device = $this->transaction(fn (): HardwareDevice => HardwareDevice::create([
            'school_id' => $schoolId, 'device_type' => $deviceType, 'location' => $location,
            'purpose' => $purpose, 'api_client_id' => $issued['client']->id, 'status' => 'offline',
        ]));

        return ['device' => $device, 'plaintextKey' => $issued['plaintextKey']];
    }
}
