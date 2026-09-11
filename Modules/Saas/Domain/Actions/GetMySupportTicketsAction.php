<?php

declare(strict_types=1);

namespace Modules\Saas\Domain\Actions;

use Illuminate\Database\Eloquent\Collection;
use Modules\Core\Domain\Actions\Action;
use Modules\Saas\Models\SupportTicket;

/**
 * ACT-GetMySupportTickets (Book J SAA-03 §5 — `GET /api/v1/support/tickets/mine`).
 * The narrow read path a school user gets onto their own tickets in
 * the vendor's queue — filtered by `raisedByUserId` alone, never a
 * lookup by ticket id or tenant id, so a user can never resolve
 * another user's ticket by guessing an id.
 */
final class GetMySupportTicketsAction extends Action
{
    /**
     * @return Collection<int, SupportTicket>
     */
    public function execute(int $raisedByUserId): Collection
    {
        return SupportTicket::where('raised_by_user_id', $raisedByUserId)
            ->latest('id')
            ->get();
    }
}
