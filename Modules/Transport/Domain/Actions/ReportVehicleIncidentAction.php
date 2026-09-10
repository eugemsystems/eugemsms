<?php

declare(strict_types=1);

namespace Modules\Transport\Domain\Actions;

use Illuminate\Validation\ValidationException;
use Modules\Core\Domain\Actions\Action;
use Modules\Core\Domain\Actions\Notifications\DispatchNotificationAction;
use Modules\Core\Domain\DataObjects\Notifications\DispatchNotificationData;
use Modules\People\Models\Student;
use Modules\People\Models\StudentGuardian;
use Modules\Transport\Domain\DataObjects\ReportVehicleIncidentData;
use Modules\Transport\Domain\Events\VehicleIncidentReported;
use Modules\Transport\Models\VehicleIncident;
use Modules\Welfare\Domain\Actions\RecordHealthIncidentAction;
use Modules\Welfare\Domain\DataObjects\RecordHealthIncidentData;
use Throwable;

/**
 * ACT-ReportVehicleIncident (Book H2 OPS-01 §4/BR-OPS-01-017). Real
 * `BRD-06` wiring: an incident with injuries opens a genuine
 * `HealthIncident` per learner involved through `Modules\Welfare`'s
 * own `RecordHealthIncidentAction` — not a shadow record kept
 * independently. Guardians of every learner involved are notified
 * immediately whenever there are injuries, non-blocking.
 */
final class ReportVehicleIncidentAction extends Action
{
    public function __construct(
        private readonly RecordHealthIncidentAction $recordHealthIncident,
        private readonly DispatchNotificationAction $dispatchNotification,
    ) {}

    public function execute(ReportVehicleIncidentData $data): VehicleIncident
    {
        if ($data->injuries && ($data->learnersInvolved !== null && $data->learnersInvolved !== []) && $data->termId === null) {
            throw ValidationException::withMessages([
                'termId' => 'A term is required to open BRD-06 health incidents for the learners involved.',
            ]);
        }

        return $this->transaction(function () use ($data): VehicleIncident {
            $incident = VehicleIncident::create([
                'school_id' => $data->schoolId,
                'vehicle_id' => $data->vehicleId,
                'driver_id' => $data->driverId,
                'trip_id' => $data->tripId,
                'incident_type' => $data->incidentType,
                'occurred_at' => $data->occurredAt,
                'location' => $data->location,
                'description' => $data->description,
                'learners_involved' => $data->learnersInvolved,
                'injuries' => $data->injuries,
                'police_report_number' => $data->policeReportNumber,
                'estimated_damage_minor' => $data->estimatedDamageMinor,
                'photo_file_ids' => $data->photoFileIds,
                'reported_by' => $data->reportedByUserId,
                'status' => 'reported',
            ]);

            if ($data->injuries && $data->learnersInvolved !== null) {
                foreach ($data->learnersInvolved as $studentId) {
                    $this->recordHealthIncident->execute(new RecordHealthIncidentData(
                        schoolId: $data->schoolId,
                        termId: (int) $data->termId,
                        studentId: $studentId,
                        incidentType: 'other',
                        occurredAt: $data->occurredAt,
                        location: $data->location,
                        description: "Vehicle incident ({$data->incidentType}): {$data->description}",
                        severity: 'moderate',
                        reportedByUserId: $data->reportedByUserId,
                    ));

                    $this->notifyGuardian($incident, $studentId);
                }
            }

            event(new VehicleIncidentReported($incident));

            return $incident;
        });
    }

    private function notifyGuardian(VehicleIncident $incident, int $studentId): void
    {
        $student = Student::find($studentId);

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
                notificationKey: 'transport.vehicle_incident_guardian_notified',
                recipientType: 'guardian',
                addresses: ['sms' => (string) $guardian->primary_phone, 'email' => (string) $guardian->email],
                context: ['student' => ['first_name' => $student->first_name, 'last_name' => $student->last_name], 'incident_type' => $incident->incident_type],
                recipientId: $guardian->id,
                relatedType: 'vehicle_incident',
                relatedId: $incident->id,
                urgent: true,
            ));
        } catch (Throwable) {
            // Non-blocking.
        }
    }
}
