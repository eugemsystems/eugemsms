<?php

declare(strict_types=1);

namespace Modules\Welfare\Domain\Actions;

use Modules\Core\Domain\Actions\Action;
use Modules\Core\Domain\Actions\Notifications\DispatchNotificationAction;
use Modules\Core\Domain\DataObjects\Notifications\DispatchNotificationData;
use Modules\Core\Domain\Support\Settings\ScopeChain;
use Modules\Core\Domain\Support\Settings\SettingResolver;
use Modules\People\Models\Staff;
use Modules\Welfare\Domain\DataObjects\ReportSafeguardingConcernData;
use Modules\Welfare\Domain\Events\ConcernReported;
use Modules\Welfare\Domain\Events\ImmediateRiskConcern;
use Modules\Welfare\Models\SafeguardingConcern;
use Throwable;

/**
 * ACT-ReportSafeguardingConcern (Book G BRD-08 §2/§4/BR-BRD-08-009/010).
 * Deliberately low-friction: no approval to submit, minimal required
 * fields — the DTO itself only requires source/category/description/
 * timestamp. `immediate_risk` alerts the lead and deputy immediately,
 * on every channel, bypassing quiet hours and cost caps.
 */
final class ReportSafeguardingConcernAction extends Action
{
    public function __construct(
        private readonly SettingResolver $settings,
        private readonly DispatchNotificationAction $dispatchNotification,
    ) {}

    public function execute(ReportSafeguardingConcernData $data): SafeguardingConcern
    {
        return $this->transaction(function () use ($data): SafeguardingConcern {
            $concern = SafeguardingConcern::create([
                'school_id' => $data->schoolId,
                'student_id' => $data->studentId,
                'reported_at' => $data->reportedAt,
                'report_source' => $data->reportSource,
                'reporter_user_id' => $data->reporterUserId,
                'concern_category' => $data->concernCategory,
                'description' => $data->description,
                'immediate_risk' => $data->immediateRisk,
                'initial_action_taken' => $data->initialActionTaken,
                'triage_status' => 'awaiting_triage',
            ]);

            event(new ConcernReported($concern));

            if ($data->immediateRisk) {
                event(new ImmediateRiskConcern($concern));
                $this->alertStaffSetting($concern, 'safeguarding.lead_staff_id');
                $this->alertStaffSetting($concern, 'safeguarding.deputy_lead_staff_id');
            }

            return $concern;
        });
    }

    private function alertStaffSetting(SafeguardingConcern $concern, string $settingKey): void
    {
        $scope = new ScopeChain(schoolId: $concern->school_id);
        $staffId = $this->settings->get($settingKey, $scope);

        if ($staffId === null) {
            return;
        }

        $staff = Staff::find((int) $staffId);

        if ($staff?->user_id === null) {
            return;
        }

        try {
            $this->dispatchNotification->execute(new DispatchNotificationData(
                schoolId: $concern->school_id,
                notificationKey: 'safeguarding.immediate_risk_concern',
                recipientType: 'staff',
                addresses: ['email' => (string) ($staff->work_email ?? $staff->personal_email)],
                context: ['concern_category' => $concern->concern_category],
                recipientId: $staff->user_id,
                relatedType: 'safeguarding_concern',
                relatedId: $concern->id,
                urgent: true,
            ));
        } catch (Throwable) {
            // BR-BRD-08-010 — unconditional in intent; the concern
            // itself is already durable regardless of dispatch success.
        }
    }
}
