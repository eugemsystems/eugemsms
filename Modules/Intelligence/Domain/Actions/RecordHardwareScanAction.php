<?php

declare(strict_types=1);

namespace Modules\Intelligence\Domain\Actions;

use Illuminate\Database\Eloquent\ModelNotFoundException;
use InvalidArgumentException;
use Modules\Core\Domain\Actions\Action;
use Modules\Core\Domain\Exceptions\InsufficientScopeException;
use Modules\Intelligence\Domain\Registry\HardwareScanRouteRegistry;
use Modules\Intelligence\Models\HardwareDevice;
use Modules\People\Models\Student;

/**
 * ACT-RecordHardwareScan (Book J INT-04 §3 ⭐/BR-INT-04-007/008
 * (AC-INT-04-001/003)). Re-checks the device's OWN credential — active,
 * carrying exactly the `{purpose}:write` ability its `purpose` implies
 * — every single call, never trusting that issuance-time validation
 * alone is enough (the same re-check-every-time discipline
 * `ExecuteCustomReportAction` already established for user permissions).
 * Routes through `HardwareScanRouteRegistry` to the owning module's own
 * real Action — there is no separate, lighter-touch write path here.
 */
final class RecordHardwareScanAction extends Action
{
    protected bool $transactional = false;

    /**
     * @param  array<string, mixed>  $context  purpose-specific extra data the target route needs (e.g. `gate`'s own `direction`)
     */
    public function execute(string $deviceUlid, string $tag, int $targetId, int $recordedByUserId, array $context = []): mixed
    {
        $device = HardwareDevice::where('ulid', $deviceUlid)->firstOrFail();
        $client = $device->apiClient;

        if ($client === null || ! $client->is_active) {
            throw new InsufficientScopeException('This device credential is not active.');
        }

        $ability = "{$device->purpose}:write";

        if (! $client->hasAbility($ability)) {
            throw new InsufficientScopeException("This device's credential does not carry the [{$ability}] ability.");
        }

        $route = HardwareScanRouteRegistry::get((string) $device->purpose)
            ?? throw new InvalidArgumentException("Purpose '{$device->purpose}' has no registered scan route.");

        $student = Student::where('school_id', $device->school_id)->where('rfid_tag', $tag)->first()
            ?? throw new ModelNotFoundException("No student found for the scanned tag at school {$device->school_id}.");

        return ($route->resolver)($student, $targetId, "hardware:{$device->device_type}", $recordedByUserId, $context);
    }
}
