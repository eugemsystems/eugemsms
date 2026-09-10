<?php

declare(strict_types=1);

namespace Modules\Sport\Domain\Actions;

use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Modules\Core\Domain\Actions\Action;
use Modules\Sport\Domain\Events\EquipmentOverdue;
use Modules\Sport\Models\EquipmentIssue;

/**
 * ACT-CheckOverdueEquipment (Book H2 OPS-07 §3/BR-OPS-07-011 — "must
 * be returned at season end"). Mirrors this book's own
 * `Modules\Security\Domain\Actions\CheckOverdueKeysAction` (OPS-06)
 * pattern: a scan a scheduler calls periodically, firing one event
 * per still-outstanding issue past its expected return date.
 */
final class CheckOverdueEquipmentAction extends Action
{
    /**
     * @return Collection<int, EquipmentIssue>
     */
    public function execute(int $schoolId): Collection
    {
        $overdue = EquipmentIssue::where('school_id', $schoolId)
            ->whereNull('returned_at')
            ->whereNotNull('expected_return_on')
            ->whereDate('expected_return_on', '<', Carbon::now()->toDateString())
            ->get();

        foreach ($overdue as $issue) {
            event(new EquipmentOverdue($issue));
        }

        return $overdue;
    }
}
