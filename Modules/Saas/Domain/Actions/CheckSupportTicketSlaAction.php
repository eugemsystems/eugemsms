<?php

declare(strict_types=1);

namespace Modules\Saas\Domain\Actions;

use App\Models\User;
use Illuminate\Support\Carbon;
use Modules\Core\Domain\Actions\Action;
use Modules\Core\Domain\Actions\Notifications\DispatchNotificationAction;
use Modules\Core\Domain\DataObjects\Notifications\DispatchNotificationData;
use Modules\Core\Domain\Support\Settings\ScopeChain;
use Modules\Core\Domain\Support\Settings\SettingResolver;
use Modules\Saas\Models\SupportTicket;
use Throwable;

/**
 * ACT-CheckSupportTicketSla (Book J SAA-03 §4/BR-SAA-03-004). Mirrors
 * `Modules\Comms\Domain\Actions\CheckComplaintSlaAction`'s own
 * approaching/breached tiers, meant to run on the same schedule.
 * Notifications need a `schoolId` for `CORE-09`'s own scoping — a
 * tenant-level ticket with no `school_id` (`raisedByUserId`'s own
 * `primarySchool()` stands in) is the one difference from the
 * `COM-08` original.
 */
final class CheckSupportTicketSlaAction extends Action
{
    protected bool $transactional = false;

    public function __construct(
        private readonly SettingResolver $settings,
        private readonly DispatchNotificationAction $dispatchNotification,
    ) {}

    /**
     * @return array{approaching: bool, breached: bool}
     */
    public function execute(int $ticketId): array
    {
        $ticket = SupportTicket::findOrFail($ticketId);

        if (in_array($ticket->status, ['resolved', 'closed'], true) || $ticket->assigned_vendor_staff_id === null) {
            return ['approaching' => false, 'breached' => false];
        }

        $warningHours = (int) $this->settings->get('saas.support_sla_warning_hours_before', new ScopeChain);
        $breached = Carbon::now()->greaterThanOrEqualTo($ticket->sla_due_at);
        $approaching = ! $breached && Carbon::now()->greaterThanOrEqualTo($ticket->sla_due_at->copy()->subHours($warningHours));

        if ($breached) {
            $this->notify($ticket, 'saas.support_ticket_sla_breached');
        } elseif ($approaching) {
            $this->notify($ticket, 'saas.support_ticket_sla_approaching');
        }

        return ['approaching' => $approaching, 'breached' => $breached];
    }

    private function notify(SupportTicket $ticket, string $notificationKey): void
    {
        $assignee = User::find($ticket->assigned_vendor_staff_id);
        $schoolId = $ticket->school_id ?? $ticket->raisedBy?->primarySchool()?->id;

        if ($assignee === null || $schoolId === null) {
            return;
        }

        try {
            $this->dispatchNotification->execute(new DispatchNotificationData(
                schoolId: $schoolId,
                notificationKey: $notificationKey,
                recipientType: 'user',
                addresses: ['email' => $assignee->email],
                recipientId: $assignee->id,
                relatedType: 'support_ticket',
                relatedId: $ticket->id,
                dedupeWindowMinutes: 10080,
            ));
        } catch (Throwable) {
            // A notification-dispatch failure never blocks the SLA check itself.
        }
    }
}
