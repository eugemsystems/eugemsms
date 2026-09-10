<?php

declare(strict_types=1);

namespace Modules\Welfare\Domain\Actions;

use Illuminate\Support\Carbon;
use Modules\Core\Domain\Actions\Action;
use Modules\Core\Domain\Actions\Notifications\DispatchNotificationAction;
use Modules\Core\Domain\DataObjects\Notifications\DispatchNotificationData;
use Modules\Core\Domain\Support\Settings\ScopeChain;
use Modules\Core\Domain\Support\Settings\SettingResolver;
use Modules\People\Models\Staff;
use Modules\People\Models\Student;
use Modules\People\Models\StudentGuardian;
use Modules\Welfare\Domain\DataObjects\AdmitToSickBayData;
use Modules\Welfare\Domain\Events\OutbreakThresholdReached;
use Modules\Welfare\Domain\Events\SickBayAdmissionRecorded;
use Modules\Welfare\Models\SickBayAdmission;
use Throwable;

/**
 * ACT-AdmitToSickBay (Book G BRD-06 §4/§7 ⭐⭐/BR-BRD-06-015/016/018/
 * 023/AC-BRD-06-006/007/011). Closes the `sick_bay` roll-status stub
 * (BRD-02's `OpenRollCallAction` now queries `sick_bay_admissions`
 * directly). A `serious`/`emergency` admission notifies the guardian
 * immediately and unconditionally — never subject to quiet hours or
 * cost caps, matching `boarding.missing_learner_escalation`'s own
 * `urgent: true` convention.
 */
final class AdmitToSickBayAction extends Action
{
    public function __construct(
        private readonly SettingResolver $settings,
        private readonly DispatchNotificationAction $dispatchNotification,
    ) {}

    public function execute(AdmitToSickBayData $data): SickBayAdmission
    {
        return $this->transaction(function () use ($data): SickBayAdmission {
            $notifyImmediately = in_array($data->severity, SickBayAdmission::NOTIFY_IMMEDIATELY_SEVERITIES, true);

            $admission = SickBayAdmission::create([
                'school_id' => $data->schoolId,
                'term_id' => $data->termId,
                'student_id' => $data->studentId,
                'admitted_at' => $data->admittedAt,
                'admitted_by' => $data->admittedByUserId,
                'presenting_complaint' => $data->presentingComplaint,
                'initial_observations' => $data->initialObservations,
                'bed_reference' => $data->bedReference,
                'is_isolation' => $data->isIsolation,
                'isolation_reason' => $data->isolationReason,
                'severity' => $data->severity,
                'guardian_notified_at' => $notifyImmediately ? Carbon::now() : null,
                'guardian_notified_by' => $notifyImmediately ? $data->admittedByUserId : null,
                'expected_discharge_at' => $data->expectedDischargeAt,
                'excused_from_lessons' => true,
                'excused_from_activity' => true,
                'status' => 'admitted',
            ]);

            event(new SickBayAdmissionRecorded($admission));

            if ($notifyImmediately) {
                $this->notifyGuardian($admission);
            }

            $this->checkOutbreakThreshold($admission);

            return $admission;
        });
    }

    private function notifyGuardian(SickBayAdmission $admission): void
    {
        $student = Student::find($admission->student_id);

        if ($student === null) {
            return;
        }

        $link = StudentGuardian::query()
            ->where('student_id', $student->id)
            ->where('is_primary_contact', true)
            ->where('status', 'active')
            ->with('guardian')
            ->first();

        if ($link === null || $link->guardian === null) {
            return;
        }

        $guardian = $link->guardian;

        try {
            $this->dispatchNotification->execute(new DispatchNotificationData(
                schoolId: $admission->school_id,
                notificationKey: 'health.sick_bay_admission_serious',
                recipientType: 'guardian',
                addresses: ['sms' => (string) $guardian->primary_phone, 'email' => (string) $guardian->email],
                context: [
                    'student' => ['first_name' => $student->first_name, 'last_name' => $student->last_name],
                    'severity' => $admission->severity,
                ],
                recipientId: $guardian->id,
                relatedType: 'sick_bay_admission',
                relatedId: $admission->id,
                urgent: true,
            ));
        } catch (Throwable) {
            // BR-BRD-06-016 — notification is unconditional in intent,
            // but a dispatch failure never blocks the admission itself.
        }
    }

    /**
     * BR-BRD-06-023/AC-BRD-06-011.
     */
    private function checkOutbreakThreshold(SickBayAdmission $admission): void
    {
        $scope = new ScopeChain(schoolId: $admission->school_id);
        $windowDays = (int) $this->settings->get('health.outbreak_window_days', $scope);
        $threshold = (int) $this->settings->get('health.outbreak_threshold_cases', $scope);

        $windowStart = $admission->admitted_at->copy()->subDays($windowDays);

        $caseCount = SickBayAdmission::query()
            ->where('school_id', $admission->school_id)
            ->where('presenting_complaint', $admission->presenting_complaint)
            ->where('admitted_at', '>=', $windowStart)
            ->where('admitted_at', '<=', $admission->admitted_at)
            ->count();

        if ($caseCount < $threshold) {
            return;
        }

        event(new OutbreakThresholdReached($admission->school_id, $admission->presenting_complaint, $caseCount));

        $this->notifyStaffSetting('health.nurse_staff_id', $admission, $caseCount);
        $this->notifyStaffSetting('health.head_staff_id', $admission, $caseCount);
    }

    private function notifyStaffSetting(string $settingKey, SickBayAdmission $admission, int $caseCount): void
    {
        $scope = new ScopeChain(schoolId: $admission->school_id);
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
                schoolId: $admission->school_id,
                notificationKey: 'health.outbreak_threshold_reached',
                recipientType: 'staff',
                addresses: ['email' => (string) ($staff->work_email ?? $staff->personal_email)],
                context: ['presenting_complaint' => $admission->presenting_complaint, 'case_count' => $caseCount],
                recipientId: $staff->user_id,
                relatedType: 'sick_bay_admission',
                relatedId: $admission->id,
                urgent: true,
            ));
        } catch (Throwable) {
            // Non-blocking — the outbreak event itself is already durable.
        }
    }
}
