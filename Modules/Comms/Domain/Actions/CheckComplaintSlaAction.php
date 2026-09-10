<?php

declare(strict_types=1);

namespace Modules\Comms\Domain\Actions;

use Illuminate\Support\Carbon;
use Modules\Comms\Models\Complaint;
use Modules\Core\Domain\Actions\Action;
use Modules\Core\Domain\Actions\Notifications\DispatchNotificationAction;
use Modules\Core\Domain\DataObjects\Notifications\DispatchNotificationData;
use Modules\Core\Domain\Support\Settings\ScopeChain;
use Modules\Core\Domain\Support\Settings\SettingResolver;
use Modules\People\Models\Staff;
use Throwable;

/**
 * ACT-CheckComplaintSla (Book I COM-08 §3 ⭐/BR-COM-08-004). Meant to
 * run on a schedule against every open complaint, mirroring
 * `Modules\Comms\Domain\Actions\EscalateUnreadUrgentNoticeAction`'s
 * own "meant to run on a schedule" note. Two tiers, each dispatched at
 * most once via `DispatchNotificationAction`'s own dedupe (no
 * hand-rolled "already warned" flag needed): approaching alerts the
 * assignee; breached alerts the assignee's own manager
 * (`Staff.reports_to_staff_id` — a real, already-existing field, not
 * a new escalation-role concept).
 */
final class CheckComplaintSlaAction extends Action
{
    protected bool $transactional = false;

    public function __construct(
        private readonly SettingResolver $settings,
        private readonly DispatchNotificationAction $dispatchNotification,
    ) {}

    /**
     * @return array{approaching: bool, breached: bool}
     */
    public function execute(int $complaintId): array
    {
        $complaint = Complaint::findOrFail($complaintId);

        if (in_array($complaint->status, ['resolved', 'closed'], true) || $complaint->assigned_to_staff_id === null) {
            return ['approaching' => false, 'breached' => false];
        }

        $assignee = Staff::find($complaint->assigned_to_staff_id);

        if ($assignee === null) {
            return ['approaching' => false, 'breached' => false];
        }

        $warningHours = (int) $this->settings->get('complaints.sla_warning_hours_before', new ScopeChain(schoolId: $complaint->school_id));
        $breached = Carbon::now()->greaterThanOrEqualTo($complaint->sla_due_at);
        $approaching = ! $breached && Carbon::now()->greaterThanOrEqualTo($complaint->sla_due_at->copy()->subHours($warningHours));

        if ($breached) {
            $this->notifyStaff($complaint, $assignee->reports_to_staff_id, 'comms.complaint_sla_breached');
        } elseif ($approaching) {
            $this->notifyStaff($complaint, $assignee->id, 'comms.complaint_sla_approaching');
        }

        return ['approaching' => $approaching, 'breached' => $breached];
    }

    private function notifyStaff(Complaint $complaint, ?int $staffId, string $notificationKey): void
    {
        $staff = $staffId !== null ? Staff::find($staffId) : null;

        if ($staff === null || $staff->user_id === null) {
            return;
        }

        try {
            $this->dispatchNotification->execute(new DispatchNotificationData(
                schoolId: $complaint->school_id,
                notificationKey: $notificationKey,
                recipientType: 'staff',
                addresses: ['email' => (string) $staff->work_email],
                context: ['complaint' => ['number' => $complaint->complaint_number]],
                recipientId: $staff->user_id,
                relatedType: 'complaint',
                relatedId: $complaint->id,
                dedupeWindowMinutes: 10080,
            ));
        } catch (Throwable) {
            // A notification-dispatch failure never blocks the SLA check itself.
        }
    }
}
