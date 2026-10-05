<?php

declare(strict_types=1);

namespace Modules\Saas\Domain\Actions;

use App\Models\User;
use InvalidArgumentException;
use Modules\Core\Domain\Actions\Action;
use Modules\Core\Domain\Support\Auth\UserType;
use Modules\Saas\Models\SupportTicket;

/**
 * ACT-AssignSupportTicket (Book J SAA-03 §5, support queue). A ticket is
 * owned by VENDOR staff only — a school user can never be made its
 * assignee — and a closed ticket is not reassigned. Cross-tenant: runs with
 * the school scope lifted, which only the vendor console may do.
 */
final class AssignSupportTicketAction extends Action
{
    public function execute(int $ticketId, int $vendorUserId): SupportTicket
    {
        $ticket = SupportTicket::query()->withoutGlobalScopes()->findOrFail($ticketId);

        if (! User::query()->where('user_type', UserType::Vendor)->whereKey($vendorUserId)->exists()) {
            throw new InvalidArgumentException('A ticket can only be assigned to vendor staff.');
        }

        if ($ticket->status === 'closed') {
            throw new InvalidArgumentException('A closed ticket cannot be reassigned.');
        }

        return $this->transaction(function () use ($ticket, $vendorUserId): SupportTicket {
            $ticket->update(['assigned_vendor_staff_id' => $vendorUserId, 'status' => $ticket->status === 'open' ? 'in_progress' : $ticket->status]);

            return $ticket->fresh();
        });
    }
}
