<?php

declare(strict_types=1);

namespace Modules\Saas\Domain\Actions;

use Illuminate\Support\Carbon;
use InvalidArgumentException;
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
        if (! in_array($status, ['investigating', 'identified', 'monitoring', 'resolved'], true) || trim($message) === '' || mb_strlen($message) > 2000) {
            throw new InvalidArgumentException('An update needs a valid status and a message.');
        }

        $incident = IncidentStatusEntry::query()->findOrFail($incidentId);

        if ($incident->status === 'resolved') {
            throw new InvalidArgumentException('A resolved incident takes no further updates; open a new one.');
        }

        return $this->transaction(function () use ($incident, $status, $message): IncidentStatusEntry {
            $updates = $incident->updates;
            $updates[] = ['at' => Carbon::now()->toIso8601String(), 'message' => $message, 'status' => $status];

            $incident->update(['status' => $status, 'updates' => $updates]);

            return $incident->fresh();
        });
    }
}
