<?php

declare(strict_types=1);

namespace Modules\Boarding\Domain\Actions;

use Modules\Boarding\Domain\DataObjects\CreateMovementCheckpointData;
use Modules\Boarding\Models\MovementCheckpoint;
use Modules\Core\Domain\Actions\Action;

/**
 * ACT-CreateMovementCheckpoint (Book F BRD-02 §2). Gap-filling,
 * admin-UI pass: no Action anywhere ever created a
 * `movement_checkpoints` row before this (verified: `grep -rn
 * "MovementCheckpoint::create" Modules/Boarding` returns nothing
 * outside this file) — every existing row came from
 * `TenantModelRegistry`'s own factory call. `RecordCheckpointMovementAction`
 * needs a real checkpoint to scan against; this closes that gap the
 * same way `CreateHostelWingAction` closes BRD-01's.
 */
final class CreateMovementCheckpointAction extends Action
{
    public function execute(CreateMovementCheckpointData $data): MovementCheckpoint
    {
        return $this->transaction(fn (): MovementCheckpoint => MovementCheckpoint::create([
            'school_id' => $data->schoolId,
            'code' => $data->code,
            'name' => $data->name,
            'checkpoint_type' => $data->checkpointType,
            'hardware_device_id' => $data->hardwareDeviceId,
            'is_boundary' => $data->isBoundary,
            'is_active' => true,
        ]));
    }
}
