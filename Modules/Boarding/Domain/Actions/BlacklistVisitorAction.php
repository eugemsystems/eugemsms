<?php

declare(strict_types=1);

namespace Modules\Boarding\Domain\Actions;

use Modules\Boarding\Domain\DataObjects\BlacklistVisitorData;
use Modules\Boarding\Models\Visitor;
use Modules\Core\Domain\Actions\Action;

/**
 * ACT-BlacklistVisitor (Book F BRD-03 §4/BR-BRD-03-018).
 */
final class BlacklistVisitorAction extends Action
{
    public function execute(BlacklistVisitorData $data): Visitor
    {
        $visitor = Visitor::findOrFail($data->visitorId);

        return $this->transaction(fn (): Visitor => tap($visitor)->update([
            'is_blacklisted' => true,
            'blacklist_reason' => $data->reason,
            'blacklisted_by' => $data->blacklistedByUserId,
        ]));
    }
}
