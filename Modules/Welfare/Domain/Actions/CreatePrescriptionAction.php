<?php

declare(strict_types=1);

namespace Modules\Welfare\Domain\Actions;

use Illuminate\Validation\ValidationException;
use Modules\Core\Domain\Actions\Action;
use Modules\Welfare\Domain\DataObjects\CreatePrescriptionData;
use Modules\Welfare\Models\MedicalConsent;
use Modules\Welfare\Models\Prescription;

/**
 * ACT-CreatePrescription (Book G BRD-06 §2/BR-BRD-06-012). Prescribed
 * medication requires a prescription-specific consent — a standing OTC
 * consent does not substitute.
 */
final class CreatePrescriptionAction extends Action
{
    public function execute(CreatePrescriptionData $data): Prescription
    {
        $consent = MedicalConsent::findOrFail($data->guardianConsentId);

        if ($consent->consent_type !== 'prescribed_medication' || ! $consent->isValidNow()) {
            throw ValidationException::withMessages([
                'guardianConsentId' => "Consent #{$consent->id} is not a valid, unwithdrawn prescribed_medication consent.",
            ]);
        }

        return $this->transaction(fn (): Prescription => Prescription::create([
            'school_id' => $data->schoolId,
            'student_id' => $data->studentId,
            'medication_name' => $data->medicationName,
            'dose' => $data->dose,
            'frequency' => $data->frequency,
            'route' => $data->route,
            'prescribed_by' => $data->prescribedBy,
            'prescribed_on' => $data->prescribedOn->toDateString(),
            'starts_on' => $data->startsOn->toDateString(),
            'ends_on' => $data->endsOn?->toDateString(),
            'is_prn' => $data->isPrn,
            'max_doses_per_day' => $data->maxDosesPerDay,
            'prescription_file_id' => $data->prescriptionFileId,
            'guardian_consent_id' => $consent->id,
            'is_self_administered' => $data->isSelfAdministered,
            'storage_location' => $data->storageLocation,
            'status' => 'active',
        ]));
    }
}
