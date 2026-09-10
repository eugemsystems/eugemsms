<?php

declare(strict_types=1);

namespace Modules\Boarding\Domain\Actions;

use Modules\Boarding\Domain\Events\VisitorNotSignedOut;
use Modules\Boarding\Models\VisitorLogEntry;
use Modules\Core\Domain\Actions\Action;

/**
 * ACT-CheckVisitorNotSignedOut (Book F BRD-03 §5/BR-BRD-03-020/
 * AC-BRD-03-009). "Nobody stays on a boarding campus overnight
 * unaccounted for." Meant to run once daily at the configured hour
 * against every visitor still signed in; wiring that schedule entry
 * is a deployment step, mirroring `CheckOverdueExeatAction`'s own note.
 */
final class CheckVisitorNotSignedOutAction extends Action
{
    public function execute(int $visitorLogId): bool
    {
        $entry = VisitorLogEntry::findOrFail($visitorLogId);

        if ($entry->signed_out_at !== null) {
            return false;
        }

        event(new VisitorNotSignedOut($entry));

        return true;
    }
}
