<?php

declare(strict_types=1);

namespace Modules\Welfare\Domain\Actions;

use Illuminate\Validation\ValidationException;
use Modules\Core\Domain\Actions\Action;
use Modules\Core\Domain\Actions\Notifications\DispatchNotificationAction;
use Modules\Core\Domain\DataObjects\Notifications\DispatchNotificationData;
use Modules\Core\Domain\Exceptions\InvalidStateTransitionException;
use Modules\People\Models\Student;
use Modules\People\Models\StudentGuardian;
use Modules\Welfare\Domain\DataObjects\AdministerMedicationData;
use Modules\Welfare\Domain\Events\EmergencyTreatmentProceeded;
use Modules\Welfare\Domain\Events\MedicationAdministered;
use Modules\Welfare\Domain\Events\MedicationOmitted;
use Modules\Welfare\Models\ClinicStock;
use Modules\Welfare\Models\ControlledStockLogEntry;
use Modules\Welfare\Models\MedicalConsent;
use Modules\Welfare\Models\MedicationAdministration;
use Modules\Welfare\Models\Prescription;
use Throwable;

/**
 * ACT-AdministerMedication (Book G BRD-06 §5/§7 ⭐⭐/BR-BRD-06-010/011/
 * 012/013/014/019/022/AC-BRD-06-003/005/010/012). The medication round
 * screen's one-tap record, minus the UI: consent check, expiry check,
 * witness prompt, one append-only record. `emergencyProvision` is the
 * one path that skips the consent check — and it is recorded loudly,
 * never silently (BR-BRD-06-019).
 */
final class AdministerMedicationAction extends Action
{
    public function __construct(
        private readonly DispatchNotificationAction $dispatchNotification,
    ) {}

    public function execute(AdministerMedicationData $data): MedicationAdministration
    {
        if ($data->outcome !== 'given' && $data->omissionReason === null) {
            throw ValidationException::withMessages([
                'omissionReason' => "Outcome '{$data->outcome}' requires a reason — silence is not an acceptable record (BR-BRD-06-014).",
            ]);
        }

        if ($data->emergencyProvision && $data->emergencyDecisionMakerUserId === null) {
            throw ValidationException::withMessages([
                'emergencyDecisionMakerUserId' => 'Emergency provision requires naming the deciding staff member.',
            ]);
        }

        $consentReference = $data->emergencyProvision
            ? "emergency_provision:{$data->emergencyDecisionMakerUserId}"
            : $this->resolveConsentReference($data);

        $stock = $data->clinicStockId === null ? null : ClinicStock::findOrFail($data->clinicStockId);

        if ($stock !== null && $stock->isExpired()) {
            throw ValidationException::withMessages([
                'clinicStockId' => "Stock #{$stock->id} ({$stock->name}) has expired and cannot be selected (BR-BRD-06-022).",
            ]);
        }

        if ($stock !== null && $stock->is_controlled && ($data->witnessedByUserId === null || $data->witnessedByUserId === $data->administeredByUserId)) {
            throw new InvalidStateTransitionException(
                "Controlled medication {$stock->name} requires a second, different staff member as witness (BR-BRD-06-013).",
                ['clinic_stock_id' => $stock->id],
            );
        }

        return $this->transaction(function () use ($data, $consentReference, $stock): MedicationAdministration {
            $administration = MedicationAdministration::create([
                'school_id' => $data->schoolId,
                'student_id' => $data->studentId,
                'admission_id' => $data->admissionId,
                'prescription_id' => $data->prescriptionId,
                'medication_name' => $data->medicationName,
                'dose' => $data->dose,
                'route' => $data->route,
                'administered_at' => $data->administeredAt,
                'administered_by' => $data->administeredByUserId,
                'witnessed_by' => $data->witnessedByUserId,
                'batch_number' => $data->batchNumber,
                'expiry_date' => $data->expiryDate?->toDateString(),
                'consent_reference' => $consentReference,
                'outcome' => $data->outcome,
                'omission_reason' => $data->omissionReason,
                'adverse_reaction' => $data->adverseReaction,
                'notes' => $data->notes,
            ]);

            if ($stock !== null) {
                $this->recordStockConsumption($stock, $data, $administration);
            }

            if ($data->emergencyProvision) {
                event(new EmergencyTreatmentProceeded($administration));
                $this->notifyGuardianImmediately($data, urgent: true);
            } elseif ($data->outcome === 'given') {
                event(new MedicationAdministered($administration));
            } else {
                event(new MedicationOmitted($administration));
            }

            return $administration;
        });
    }

    private function resolveConsentReference(AdministerMedicationData $data): string
    {
        if ($data->prescriptionId !== null) {
            $prescription = Prescription::findOrFail($data->prescriptionId);
            $consent = $prescription->guardianConsent;

            if ($consent === null || ! $consent->isValidNow()) {
                throw ValidationException::withMessages([
                    'prescriptionId' => "No valid, unwithdrawn prescribed_medication consent exists for prescription #{$prescription->id}.",
                ]);
            }

            return "consent:{$consent->id}";
        }

        $consent = MedicalConsent::query()
            ->where('student_id', $data->studentId)
            ->where('consent_type', 'otc_medication')
            ->where('granted', true)
            ->whereNull('withdrawn_at')
            ->get()
            ->first(fn (MedicalConsent $c): bool => $c->isValidNow());

        if ($consent === null) {
            throw ValidationException::withMessages([
                'studentId' => "No valid, unwithdrawn otc_medication consent exists for student #{$data->studentId}.",
            ]);
        }

        return "consent:{$consent->id}";
    }

    private function recordStockConsumption(ClinicStock $stock, AdministerMedicationData $data, MedicationAdministration $administration): void
    {
        $balanceAfter = (float) $stock->quantity_on_hand - $data->stockQuantityConsumed;
        $stock->update(['quantity_on_hand' => $balanceAfter]);

        if (! $stock->is_controlled) {
            return;
        }

        ControlledStockLogEntry::create([
            'school_id' => $stock->school_id,
            'clinic_stock_id' => $stock->id,
            'action' => 'administered',
            'quantity' => $data->stockQuantityConsumed,
            'balance_after' => $balanceAfter,
            'administration_id' => $administration->id,
            'performed_by' => $data->administeredByUserId,
            'witnessed_by' => $data->witnessedByUserId,
            'occurred_at' => $data->administeredAt,
        ]);
    }

    private function notifyGuardianImmediately(AdministerMedicationData $data, bool $urgent): void
    {
        $student = Student::find($data->studentId);

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
                schoolId: $data->schoolId,
                notificationKey: 'health.emergency_treatment_proceeded',
                recipientType: 'guardian',
                addresses: ['sms' => (string) $guardian->primary_phone, 'email' => (string) $guardian->email],
                context: ['student' => ['first_name' => $student->first_name, 'last_name' => $student->last_name]],
                recipientId: $guardian->id,
                relatedType: 'medication_administration',
                relatedId: $data->studentId,
                urgent: $urgent,
            ));
        } catch (Throwable) {
            // BR-BRD-06-019 — the record itself is already durable
            // regardless of whether the notification lands.
        }
    }
}
