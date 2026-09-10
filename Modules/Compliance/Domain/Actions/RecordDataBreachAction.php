<?php

declare(strict_types=1);

namespace Modules\Compliance\Domain\Actions;

use Illuminate\Support\Carbon;
use Modules\Compliance\Domain\DataObjects\RecordDataBreachData;
use Modules\Compliance\Domain\Events\DataBreachEscalated;
use Modules\Compliance\Models\DataBreach;
use Modules\Core\Domain\Actions\Action;
use Modules\Core\Domain\Actions\Notifications\DispatchNotificationAction;
use Modules\Core\Domain\DataObjects\Notifications\DispatchNotificationData;
use Modules\Core\Domain\Support\Settings\ScopeChain;
use Modules\Core\Domain\Support\Settings\SettingResolver;
use Modules\People\Models\Staff;
use Throwable;

/**
 * ACT-RecordDataBreach (Book H3 CMP-03 §3 ⭐/BR-CMP-03-011/012
 * (AC-CMP-03-004)). A breach `includesMinors` escalates automatically:
 * the head and the safeguarding lead are notified synchronously,
 * resolved the SAME way `Modules\Welfare`'s real `SafeguardingRouterImpl`
 * already resolves its own lead — a single per-school staff-id
 * setting (`safeguarding.lead_staff_id`, reused here rather than
 * duplicated; `compliance.head_staff_id` is this module's own
 * equivalent) — not a role query, since no role-based recipient
 * resolver exists anywhere in this codebase yet. Notification failure
 * never blocks the breach record itself. `DataBreachEscalated` also
 * fires, for anything else that should react later.
 */
final class RecordDataBreachAction extends Action
{
    public function __construct(
        private readonly SettingResolver $settings,
        private readonly DispatchNotificationAction $dispatchNotification,
    ) {}

    public function execute(RecordDataBreachData $data): DataBreach
    {
        $breach = $this->transaction(fn (): DataBreach => DataBreach::create([
            'school_id' => $data->schoolId,
            'detected_at' => Carbon::now(),
            'breach_type' => $data->breachType,
            'description' => $data->description,
            'data_categories' => $data->dataCategories,
            'records_affected' => $data->recordsAffected,
            'subjects_affected' => $data->subjectsAffected,
            'includes_minors' => $data->includesMinors,
            'severity' => $data->severity,
            'status' => 'detected',
            'reported_by' => $data->reportedByUserId,
        ]));

        if ($data->includesMinors) {
            $this->escalate($breach);
        }

        return $breach;
    }

    private function escalate(DataBreach $breach): void
    {
        $scope = new ScopeChain(schoolId: $breach->school_id);

        foreach (['compliance.head_staff_id', 'safeguarding.lead_staff_id'] as $settingKey) {
            $staffId = (int) $this->settings->get($settingKey, $scope);

            if ($staffId <= 0) {
                continue;
            }

            $this->notifyStaff($breach, $staffId);
        }

        event(new DataBreachEscalated($breach));
    }

    private function notifyStaff(DataBreach $breach, int $staffId): void
    {
        $staff = Staff::find($staffId);

        if ($staff === null) {
            return;
        }

        try {
            $this->dispatchNotification->execute(new DispatchNotificationData(
                schoolId: $breach->school_id,
                notificationKey: 'compliance.data_breach_escalated',
                recipientType: 'staff',
                addresses: ['sms' => (string) $staff->primary_phone, 'email' => (string) ($staff->work_email ?? $staff->personal_email)],
                context: ['breach_type' => $breach->breach_type, 'severity' => $breach->severity, 'subjects_affected' => $breach->subjects_affected],
                recipientId: $staff->id,
                relatedType: 'data_breach',
                relatedId: $breach->id,
                urgent: true,
            ));
        } catch (Throwable) {
            // Non-blocking — the breach record is already durable.
        }
    }
}
