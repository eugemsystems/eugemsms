<?php

declare(strict_types=1);

namespace Modules\Security\Domain\Actions;

use Illuminate\Support\Carbon;
use Modules\Core\Domain\Actions\Action;
use Modules\Security\Models\Patrol;
use Modules\Security\Models\PatrolScan;

/**
 * ACT-RecordPatrolScan (Book H2 OPS-06 §2/BR-OPS-06-004).
 */
final class RecordPatrolScanAction extends Action
{
    public function execute(int $patrolId, int $checkpointId, string $method, ?string $note = null): PatrolScan
    {
        $patrol = Patrol::findOrFail($patrolId);

        return $this->transaction(function () use ($patrol, $checkpointId, $method, $note): PatrolScan {
            $scan = PatrolScan::create([
                'school_id' => $patrol->school_id,
                'patrol_id' => $patrol->id,
                'checkpoint_id' => $checkpointId,
                'scanned_at' => Carbon::now(),
                'method' => $method,
                'note' => $note,
            ]);

            $patrol->update([
                'started_at' => $patrol->started_at ?? Carbon::now(),
                'status' => 'in_progress',
                'checkpoints_scanned' => $patrol->checkpoints_scanned + 1,
            ]);

            return $scan;
        });
    }
}
