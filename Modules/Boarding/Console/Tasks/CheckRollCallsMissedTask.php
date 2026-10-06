<?php

declare(strict_types=1);

namespace Modules\Boarding\Console\Tasks;

use Modules\Boarding\Domain\Actions\CheckRollCallMissedAction;
use Modules\Boarding\Models\Hostel;
use Modules\Boarding\Models\RollCallPoint;
use Modules\Core\Domain\Contracts\ScheduledTaskHandler;
use Modules\Core\Models\School;

/**
 * Scheduled (BRD-02 BR-BRD-02-008): checks every mandatory roll-call point due today for each hostel it covers; an unconducted roll opens an incident.
 */
final class CheckRollCallsMissedTask implements ScheduledTaskHandler
{
    public function handle(School $school): string
    {
        $today = now();
        $day = strtolower($today->format('D'));
        $raised = 0;

        foreach (RollCallPoint::query()->where('is_active', true)->where('is_mandatory', true)->get() as $point) {
            if (! in_array($day, array_map('strtolower', $point->applies_on_days), true)) {
                continue;
            }

            $hostels = $point->hostel_id === null ? Hostel::query()->where('is_active', true)->pluck('id') : collect([$point->hostel_id]);

            foreach ($hostels as $hostelId) {
                if (app(CheckRollCallMissedAction::class)->execute($point->id, (int) $hostelId, $today)) {
                    $raised++;
                }
            }
        }

        return "{$raised} missed roll call(s)";
    }
}
