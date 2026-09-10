<?php

declare(strict_types=1);

namespace Modules\Boarding\Domain\Actions;

use Illuminate\Support\Carbon;
use Modules\Boarding\Domain\DataObjects\SignOutVisitorData;
use Modules\Boarding\Models\VisitorLogEntry;
use Modules\Core\Domain\Actions\Action;
use Modules\Core\Domain\Exceptions\InvalidStateTransitionException;

/**
 * ACT-SignOutVisitor (Book F BRD-03 §4). The one legitimate
 * post-creation update to a `visitor_logs` row.
 */
final class SignOutVisitorAction extends Action
{
    public function execute(SignOutVisitorData $data): VisitorLogEntry
    {
        $entry = VisitorLogEntry::findOrFail($data->visitorLogId);

        if ($entry->signed_out_at !== null) {
            throw new InvalidStateTransitionException(
                "Visitor log #{$entry->id} is already signed out.",
                ['visitor_log_id' => $entry->id],
            );
        }

        return $this->transaction(fn (): VisitorLogEntry => tap($entry)->update([
            'signed_out_at' => Carbon::now(),
            'gate_staff_out' => $data->gateStaffUserId,
        ]));
    }
}
