<?php

declare(strict_types=1);

namespace Modules\Saas\Domain\Actions;

use Illuminate\Support\Carbon;
use Modules\Core\Domain\Actions\Action;
use Modules\Saas\Models\IncidentStatusEntry;

/**
 * ACT-PostIncidentUpdate (Book J SAA-02 §2/§4). `status` moves through
 * `investigating` → `identified` → `monitoring` → `resolved`; every
 * update is appended to the timeline, never overwritten.
 */
final class PostIncidentUpdateAction extends Action
{
    public function execute(int $incidentId, string $status, string $message): IncidentStatusEntry
    {
        $incident = IncidentStatusEntry::query()->findOrFail($incidentId);

        return $this->transaction(function () use ($incident, $status, $message): IncidentStatusEntry {
            $updates = $incident->updates;
            $updates[] = ['at' => Carbon::now()->toIso8601String(), 'message' => $message, 'status' => $status];

            $incident->update(['status' => $status, 'updates' => $updates]);

            return $incident->fresh();
        });
    }
}
