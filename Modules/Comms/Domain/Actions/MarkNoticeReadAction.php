<?php

declare(strict_types=1);

namespace Modules\Comms\Domain\Actions;

use Illuminate\Support\Carbon;
use Modules\Comms\Models\Notice;
use Modules\Comms\Models\NoticeRead;
use Modules\Core\Domain\Actions\Action;

/**
 * ACT-MarkNoticeRead (Book I COM-06 §2/BR-COM-06-004). A `normal`
 * notice tracks no read receipts at all — "to keep the tracking
 * overhead proportionate" is the spec's own reasoning — so this is a
 * genuine no-op, not merely an unwritten row, for anything but
 * `important`/`urgent`. `firstOrCreate` makes a repeat call
 * idempotent against `notice_reads`'s own `UNIQUE(notice_id, user_id)`.
 */
final class MarkNoticeReadAction extends Action
{
    public function execute(int $noticeId, int $userId): ?NoticeRead
    {
        $notice = Notice::findOrFail($noticeId);

        if (! $notice->tracksReadReceipts()) {
            return null;
        }

        return $this->transaction(fn (): NoticeRead => NoticeRead::firstOrCreate(
            ['notice_id' => $noticeId, 'user_id' => $userId],
            ['school_id' => $notice->school_id, 'read_at' => Carbon::now()],
        ));
    }
}
