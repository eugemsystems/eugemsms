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
use Modules\Welfare\Domain\DataObjects\RecordHealthIncidentData;
use Modules\Welfare\Domain\Events\SeriousIncidentReported;
use Modules\Welfare\Models\HealthIncident;
use Throwable;

/**
 * ACT-RecordHealthIncident (Book G BRD-06 §4/BR-BRD-06-020/021). A
 * head injury is floored at `moderate` severity, always notifies the
 * guardian, and always requires a follow-up observation record — that
 * last part is a downstream operational step (schedule a follow-up
 * consultation/observation), recorded here only as `follow_up_required`.
 */
final class RecordHealthIncidentAction extends Action
{
    private const array SEVERITY_RANK = ['minor' => 1, 'moderate' => 2, 'serious' => 3, 'critical' => 4];

    public function __construct(
        private readonly SettingResolver $settings,
        private readonly DispatchNotificationAction $dispatchNotification,
    ) {}

    public function execute(RecordHealthIncidentData $data): HealthIncident
    {
        $isHeadInjury = $data->incidentType === 'head_injury';
        $severity = $isHeadInjury && self::SEVERITY_RANK[$data->severity] < self::SEVERITY_RANK['moderate']
            ? 'moderate'
            : $data->severity;

        return $this->transaction(function () use ($data, $severity, $isHeadInjury): HealthIncident {
            $incident = HealthIncident::create([
                'school_id' => $data->schoolId,
                'term_id' => $data->termId,
                'student_id' => $data->studentId,
                'incident_type' => $data->incidentType,
                'occurred_at' => $data->occurredAt,
                'location' => $data->location,
                'activity_at_time' => $data->activityAtTime,
                'description' => $data->description,
                'witnesses' => $data->witnesses,
                'first_aid_given' => $data->firstAidGiven,
                'first_aider_staff_id' => $data->firstAiderStaffId,
                'severity' => $severity,
                'is_reportable' => in_array($severity, HealthIncident::NOTIFY_HEAD_SEVERITIES, true),
                'follow_up_required' => $isHeadInjury,
                'photo_file_ids' => $data->photoFileIds,
                'reported_by' => $data->reportedByUserId,
            ]);

            if ($isHeadInjury || in_array($severity, HealthIncident::NOTIFY_HEAD_SEVERITIES, true)) {
                $incident->update(['guardian_notified_at' => Carbon::now()]);
                $this->notifyGuardian($incident);
            }

            if (in_array($severity, HealthIncident::NOTIFY_HEAD_SEVERITIES, true)) {
                event(new SeriousIncidentReported($incident));
                $this->notifyHead($incident);
            }

            return $incident;
        });
    }

    private function notifyGuardian(HealthIncident $incident): void
    {
        $student = Student::find($incident->student_id);

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
                schoolId: $incident->school_id,
                notificationKey: 'health.incident_guardian_notified',
                recipientType: 'guardian',
                addresses: ['sms' => (string) $guardian->primary_phone, 'email' => (string) $guardian->email],
                context: [
                    'student' => ['first_name' => $student->first_name, 'last_name' => $student->last_name],
                    'incident_type' => $incident->incident_type,
                ],
                recipientId: $guardian->id,
                relatedType: 'health_incident',
                relatedId: $incident->id,
                urgent: in_array($incident->severity, HealthIncident::NOTIFY_HEAD_SEVERITIES, true),
            ));
        } catch (Throwable) {
            // Non-blocking — the incident record is already durable.
        }
    }

    private function notifyHead(HealthIncident $incident): void
    {
        $scope = new ScopeChain(schoolId: $incident->school_id);
        $headStaffId = $this->settings->get('health.head_staff_id', $scope);

        if ($headStaffId === null) {
            return;
        }

        $staff = Staff::find((int) $headStaffId);

        if ($staff?->user_id === null) {
            return;
        }

        try {
            $this->dispatchNotification->execute(new DispatchNotificationData(
                schoolId: $incident->school_id,
                notificationKey: 'health.serious_incident_head_notified',
                recipientType: 'staff',
                addresses: ['email' => (string) ($staff->work_email ?? $staff->personal_email)],
                context: ['incident_type' => $incident->incident_type, 'severity' => $incident->severity],
                recipientId: $staff->user_id,
                relatedType: 'health_incident',
                relatedId: $incident->id,
                urgent: true,
            ));
        } catch (Throwable) {
            // Non-blocking.
        }
    }
}
