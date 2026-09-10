<?php

declare(strict_types=1);

namespace Modules\Sport\Domain\Actions;

use Illuminate\Support\Carbon;
use Modules\Core\Domain\Actions\Action;
use Modules\Core\Domain\Actions\Notifications\DispatchNotificationAction;
use Modules\Core\Domain\DataObjects\Notifications\DispatchNotificationData;
use Modules\People\Models\Student;
use Modules\People\Models\StudentGuardian;
use Modules\Sport\Domain\DataObjects\RecordFixtureInjuryData;
use Modules\Sport\Models\Fixture;
use Modules\Welfare\Domain\Actions\RecordHealthIncidentAction;
use Modules\Welfare\Domain\DataObjects\RecordHealthIncidentData;
use Modules\Welfare\Models\HealthIncident;
use Throwable;

/**
 * ACT-RecordFixtureInjury (Book H2 OPS-07 §3/BR-OPS-07-012/
 * AC-OPS-07-005). Reuses the real `BRD-06` engine —
 * `Modules\Welfare\Domain\Actions\RecordHealthIncidentAction` — for
 * the incident itself, then links it back to the fixture via the
 * additive `health_incidents.fixture_id` column (see that migration's
 * docblock). `RecordHealthIncidentAction` only notifies the guardian
 * for a head injury or `serious`/`critical` severity (Book G's own,
 * deliberately narrower policy) — AC-OPS-07-005 requires the guardian
 * notified for ANY fixture injury, so this action layers its own
 * unconditional notification on top rather than editing that
 * already-gated Book G action's severity policy.
 */
final class RecordFixtureInjuryAction extends Action
{
    public function __construct(
        private readonly RecordHealthIncidentAction $recordHealthIncident,
        private readonly DispatchNotificationAction $dispatchNotification,
    ) {}

    public function execute(RecordFixtureInjuryData $data): HealthIncident
    {
        $fixture = Fixture::findOrFail($data->fixtureId);
        $location = 'Fixture venue';

        if ($fixture->venue_name !== null) {
            $location = $fixture->venue_name;
        } elseif ($fixture->venue_id !== null) {
            $location = $fixture->venue->name;
        }

        $incident = $this->recordHealthIncident->execute(new RecordHealthIncidentData(
            schoolId: $fixture->school_id,
            termId: $data->termId,
            studentId: $data->studentId,
            incidentType: $data->incidentType,
            occurredAt: $data->occurredAt,
            location: $location,
            description: $data->description,
            severity: $data->severity,
            reportedByUserId: $data->reportedByUserId,
            activityAtTime: "Fixture vs {$fixture->opponent}",
            firstAidGiven: $data->firstAidGiven,
            firstAiderStaffId: $data->firstAiderStaffId,
        ));

        $incident = $this->transaction(fn (): HealthIncident => tap($incident)->update(['fixture_id' => $fixture->id]));

        if ($incident->guardian_notified_at === null) {
            $incident->update(['guardian_notified_at' => Carbon::now()]);
            $this->notifyGuardian($incident, $fixture);
        }

        return $incident;
    }

    private function notifyGuardian(HealthIncident $incident, Fixture $fixture): void
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

        try {
            $this->dispatchNotification->execute(new DispatchNotificationData(
                schoolId: $incident->school_id,
                notificationKey: 'sport.fixture_injury_guardian_notified',
                recipientType: 'guardian',
                addresses: ['sms' => (string) $link->guardian->primary_phone, 'email' => (string) $link->guardian->email],
                context: [
                    'student' => ['first_name' => $student->first_name, 'last_name' => $student->last_name],
                    'opponent' => $fixture->opponent,
                    'incident_type' => $incident->incident_type,
                ],
                recipientId: $link->guardian->id,
                relatedType: 'health_incident',
                relatedId: $incident->id,
                urgent: true,
            ));
        } catch (Throwable) {
            // Non-blocking — the incident record is already durable.
        }
    }
}
