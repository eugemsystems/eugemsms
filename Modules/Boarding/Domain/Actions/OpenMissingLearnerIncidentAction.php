<?php

declare(strict_types=1);

namespace Modules\Boarding\Domain\Actions;

use Illuminate\Support\Carbon;
use Modules\Boarding\Domain\Events\EscalationStepTriggered;
use Modules\Boarding\Domain\Events\LearnerMissing;
use Modules\Boarding\Models\EscalationAction as EscalationActionModel;
use Modules\Boarding\Models\EscalationProfile;
use Modules\Boarding\Models\EscalationStep;
use Modules\Boarding\Models\MissingLearnerIncident;
use Modules\Boarding\Models\RollCall;
use Modules\Boarding\Models\RollCallPoint;
use Modules\Core\Domain\Actions\Action;
use Modules\Core\Domain\Actions\Notifications\DispatchNotificationAction;
use Modules\Core\Domain\DataObjects\Notifications\DispatchNotificationData;
use Modules\People\Models\Staff;
use Modules\People\Models\Student;
use Throwable;

/**
 * ACT-OpenMissingLearnerIncident (Book F BRD-02 §2/§3 ⭐⭐/BR-BRD-02-009/
 * 011, extended by BRD-03 §5/BR-BRD-03-015 for an overdue exeat).
 * Opens immediately and fires step 1 of the resolved escalation
 * profile. The profile is resolved once, at open time, and stored on
 * the incident itself (`escalation_profile_id`) so later steps never
 * need to re-derive it through a roll call that may not exist (an
 * exeat-triggered incident has none). The "last gate scan, last class
 * register, open exeat, sick bay record" attachment BR-BRD-02-011
 * describes is limited today to what actually exists: `movement_log`
 * (this module) for the last gate scan — `ACA-04`'s class register
 * and `BRD-06`'s sick bay are not queried here; documented, not faked.
 */
final class OpenMissingLearnerIncidentAction extends Action
{
    public function __construct(
        private readonly DispatchNotificationAction $dispatchNotification,
    ) {}

    public function execute(RollCall $rollCall, Student $student): MissingLearnerIncident
    {
        $existing = MissingLearnerIncident::query()
            ->where('roll_call_id', $rollCall->id)
            ->where('student_id', $student->id)
            ->where('status', '!=', 'resolved')
            ->first();

        if ($existing !== null) {
            return $existing;
        }

        $point = RollCallPoint::findOrFail($rollCall->roll_call_point_id);

        return $this->open(
            schoolId: $rollCall->school_id,
            termId: $rollCall->term_id,
            student: $student,
            rollCallId: $rollCall->id,
            escalationProfileId: $point->escalation_profile_id,
        );
    }

    /**
     * BRD-03 §5/BR-BRD-03-015 — an overdue exeat beyond the final
     * escalation step opens an incident with no roll call at all,
     * using the school's default escalation profile.
     */
    public function executeForOverdueExeat(int $schoolId, int $termId, Student $student): MissingLearnerIncident
    {
        $existing = MissingLearnerIncident::query()
            ->where('school_id', $schoolId)
            ->where('student_id', $student->id)
            ->whereNull('roll_call_id')
            ->where('status', '!=', 'resolved')
            ->first();

        if ($existing !== null) {
            return $existing;
        }

        $defaultProfile = EscalationProfile::query()->where('school_id', $schoolId)->where('is_default', true)->first();

        return $this->open(
            schoolId: $schoolId,
            termId: $termId,
            student: $student,
            rollCallId: null,
            escalationProfileId: $defaultProfile?->id,
        );
    }

    private function open(int $schoolId, int $termId, Student $student, ?int $rollCallId, ?int $escalationProfileId): MissingLearnerIncident
    {
        return $this->transaction(function () use ($schoolId, $termId, $student, $rollCallId, $escalationProfileId): MissingLearnerIncident {
            $incident = MissingLearnerIncident::create([
                'school_id' => $schoolId,
                'term_id' => $termId,
                'student_id' => $student->id,
                'roll_call_id' => $rollCallId,
                'escalation_profile_id' => $escalationProfileId,
                'first_missed_at' => Carbon::now(),
                'current_step' => 1,
                'status' => 'open',
            ]);

            event(new LearnerMissing($incident));

            $this->triggerStep($incident, 1);

            return $incident;
        });
    }

    public function triggerStep(MissingLearnerIncident $incident, int $stepNumber): void
    {
        $step = $incident->escalation_profile_id !== null
            ? EscalationStep::query()->where('profile_id', $incident->escalation_profile_id)->where('step_number', $stepNumber)->first()
            : null;

        EscalationActionModel::create([
            'school_id' => $incident->school_id,
            'incident_id' => $incident->id,
            'step_number' => $stepNumber,
            'action_type' => 'notified',
            'occurred_at' => Carbon::now(),
        ]);

        event(new EscalationStepTriggered($incident, $stepNumber));

        if ($step?->notify_staff_id === null) {
            return;
        }

        $staff = Staff::find($step->notify_staff_id);

        if ($staff?->user_id === null) {
            return;
        }

        try {
            $this->dispatchNotification->execute(new DispatchNotificationData(
                schoolId: $incident->school_id,
                notificationKey: 'boarding.missing_learner_escalation',
                recipientType: 'staff',
                addresses: ['email' => (string) ($staff->work_email ?? $staff->personal_email)],
                context: ['step_number' => $stepNumber],
                recipientId: $staff->user_id,
                relatedType: 'missing_learner_incident',
                relatedId: $incident->id,
                urgent: true,
            ));
        } catch (Throwable) {
            // A notification-dispatch failure never blocks the ladder
            // itself — the incident and its escalation_actions row
            // are already durable; the clock keeps running regardless.
        }
    }
}
