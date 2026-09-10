<?php

declare(strict_types=1);

namespace Modules\Sport\Domain\Actions;

use Illuminate\Support\Carbon;
use Modules\Core\Domain\Actions\Action;
use Modules\Sport\Models\EquipmentIssue;

/**
 * ACT-ReturnEquipment (Book H2 OPS-07 §3/BR-OPS-07-011).
 */
final class ReturnEquipmentAction extends Action
{
    public function execute(int $issueId, ?string $conditionOnReturn = null): EquipmentIssue
    {
        $issue = EquipmentIssue::findOrFail($issueId);

        return $this->transaction(fn (): EquipmentIssue => tap($issue)->update([
            'returned_at' => Carbon::now(),
            'condition_on_return' => $conditionOnReturn,
        ]));
    }
}
