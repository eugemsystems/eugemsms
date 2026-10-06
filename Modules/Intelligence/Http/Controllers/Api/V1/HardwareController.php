<?php

declare(strict_types=1);

namespace Modules\Intelligence\Http\Controllers\Api\V1;

use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Core\Domain\Exceptions\InsufficientScopeException;
use Modules\Core\Http\Support\ApiResponse;
use Modules\Intelligence\Domain\Actions\RecordHardwareHeartbeatAction;
use Modules\Intelligence\Domain\Actions\RecordHardwareScanAction;
use Modules\Intelligence\Http\Requests\HardwareScanRequest;
use Modules\Intelligence\Models\ApiClient;
use Modules\Intelligence\Models\HardwareDevice;

/**
 * `/api/v1/hardware/*` (Book J INT-04 §3 ⭐). A device may only act as itself: the
 * `device_id` must be the device whose own credential made the call (BR-INT-04-007),
 * so one compromised reader cannot post scans or heartbeats for another. The scan is
 * routed by `RecordHardwareScanAction` to the owning module's real Action (BR-INT-04-008).
 */
final class HardwareController
{
    public function __construct(
        private readonly RecordHardwareScanAction $recordScan,
        private readonly RecordHardwareHeartbeatAction $recordHeartbeat,
    ) {}

    public function scan(HardwareScanRequest $request): JsonResponse
    {
        $data = $request->validated();
        $device = $this->ownDevice($request, $data['device_id']);

        try {
            $this->recordScan->execute(
                deviceUlid: $device->ulid,
                tag: $data['tag'],
                targetId: (int) $data['target_id'],
                recordedByUserId: $this->client($request)->created_by
                    ?? throw new InsufficientScopeException('This device has no registering user to attribute scans to.'),
                context: array_filter(['direction' => $data['direction'] ?? null, 'scanned_at' => $data['scanned_at']]),
            );
        } catch (ModelNotFoundException) {
            return ApiResponse::error('TAG_NOT_FOUND', 'The scanned tag does not match anyone at this school.', 404);
        }

        return ApiResponse::ok(['device_id' => $device->ulid, 'accepted' => true], status: 201);
    }

    public function heartbeat(Request $request, string $ulid): JsonResponse
    {
        $device = $this->ownDevice($request, $ulid);
        $device = $this->recordHeartbeat->execute($device->ulid);

        return ApiResponse::ok([
            'device_id' => $device->ulid,
            'status' => $device->status,
            'last_heartbeat_at' => $device->last_heartbeat_at?->toIso8601String(),
        ]);
    }

    private function client(Request $request): ApiClient
    {
        /** @var ApiClient $client */
        $client = $request->attributes->get('api_client');

        return $client;
    }

    private function ownDevice(Request $request, string $ulid): HardwareDevice
    {
        $client = $this->client($request);

        $device = HardwareDevice::where('ulid', $ulid)->where('api_client_id', $client->id)->first();

        if ($client->client_type !== 'hardware_device' || $device === null) {
            throw new InsufficientScopeException('This credential does not belong to that device.');
        }

        return $device;
    }
}
