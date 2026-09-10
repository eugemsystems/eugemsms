<?php

declare(strict_types=1);

namespace Modules\Security\Domain\Actions;

use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Modules\Core\Domain\Actions\Action;
use Modules\Security\Domain\Events\KeyOverdue;
use Modules\Security\Models\KeyIssue;

/**
 * ACT-CheckOverdueKeys (Book H2 OPS-06 §4/BR-OPS-06-006/007). A
 * master outstanding past its due date reports the same way any other
 * overdue key does — this check doesn't distinguish, it alerts on
 * both; the master-issuing gate itself is what
 * `IssueKeyAction`/`MasterKeyRequiresAuthorityException` enforces.
 */
final class CheckOverdueKeysAction extends Action
{
    /**
     * @return Collection<int, KeyIssue>
     */
    public function execute(int $schoolId): Collection
    {
        $today = Carbon::now()->toDateString();

        $overdue = KeyIssue::where('school_id', $schoolId)
            ->where('status', 'issued')
            ->whereNotNull('due_back_on')
            ->whereDate('due_back_on', '<', $today)
            ->get();

        foreach ($overdue as $issue) {
            $issue->update(['status' => 'overdue']);
            event(new KeyOverdue($issue));
        }

        return $overdue;
    }
}
