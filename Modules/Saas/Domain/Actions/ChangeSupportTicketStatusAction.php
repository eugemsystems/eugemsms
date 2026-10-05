<?php

declare(strict_types=1);

namespace Modules\Saas\Domain\Actions;

use InvalidArgumentException;
use Modules\Core\Domain\Actions\Action;
use Modules\Saas\Models\SupportTicket;

/**
 * ACT-ChangeSupportTicketStatus (Book J SAA-03 §2). The ticket lifecycle is
 * a small state machine — `open → in_progress ⇄ waiting_on_customer →
 * resolved → closed`, with `resolved` reopenable — and `closed` is final.
 */
final class ChangeSupportTicketStatusAction extends Action
{
    /** @var array<string, array<int, string>> */
    public const array TRANSITIONS = [
        'open' => ['in_progress', 'waiting_on_customer', 'resolved'],
        'in_progress' => ['waiting_on_customer', 'resolved'],
        'waiting_on_customer' => ['in_progress', 'resolved'],
        'resolved' => ['closed', 'open'],
        'closed' => [],
    ];

    public function execute(int $ticketId, string $status): SupportTicket
    {
        $ticket = SupportTicket::query()->withoutGlobalScopes()->findOrFail($ticketId);

        if (! in_array($status, self::TRANSITIONS[$ticket->status] ?? [], true)) {
            throw new InvalidArgumentException("A {$ticket->status} ticket cannot move to {$status}.");
        }

        return $this->transaction(function () use ($ticket, $status): SupportTicket {
            $ticket->update(['status' => $status]);

            return $ticket->fresh();
        });
    }
}
