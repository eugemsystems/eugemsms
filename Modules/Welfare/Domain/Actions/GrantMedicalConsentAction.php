<?php

declare(strict_types=1);

namespace Modules\Welfare\Domain\Actions;

use Illuminate\Validation\ValidationException;
use Modules\Core\Domain\Actions\Action;
use Modules\People\Models\StudentGuardian;
use Modules\Welfare\Domain\DataObjects\GrantMedicalConsentData;
use Modules\Welfare\Models\MedicalConsent;

/**
 * ACT-GrantMedicalConsent (Book G BRD-06 §2/BR-BRD-06-011). Only a
 * guardian holding `may_authorise_medical` on an active relationship
 * can grant consent — mirrors BRD-03's `may_authorise_exeat` check.
 */
final class GrantMedicalConsentAction extends Action
{
    public function execute(GrantMedicalConsentData $data): MedicalConsent
    {
        $link = StudentGuardian::query()
            ->where('student_id', $data->studentId)
            ->where('guardian_id', $data->guardianId)
            ->where('status', 'active')
            ->first();

        if ($link === null || ! $link->may_authorise_medical) {
            throw ValidationException::withMessages([
                'guardianId' => "Guardian #{$data->guardianId} does not hold may_authorise_medical for student #{$data->studentId}.",
            ]);
        }

        return $this->transaction(fn (): MedicalConsent => MedicalConsent::create([
            'school_id' => $data->schoolId,
            'student_id' => $data->studentId,
            'guardian_id' => $data->guardianId,
            'consent_type' => $data->consentType,
            'scope_detail' => $data->scopeDetail,
            'granted' => $data->granted,
            'granted_at' => $data->grantedAt,
            'granted_via' => $data->grantedVia,
            'witness_staff_id' => $data->witnessStaffId,
            'document_file_id' => $data->documentFileId,
            'effective_from' => $data->effectiveFrom->toDateString(),
            'effective_to' => $data->effectiveTo?->toDateString(),
        ]));
    }
}
