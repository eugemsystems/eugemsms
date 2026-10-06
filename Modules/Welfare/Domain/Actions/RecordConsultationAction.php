<?php

declare(strict_types=1);

namespace Modules\Welfare\Domain\Actions;

use Illuminate\Validation\ValidationException;
use Modules\Core\Domain\Actions\Action;
use Modules\Welfare\Domain\DataObjects\RecordConsultationData;
use Modules\Welfare\Models\Consultation;
use Modules\Welfare\Models\SickBayAdmission;

/**
 * ACT-RecordConsultation (Book G BRD-06 §3). Captures a clinical consultation. The
 * complaint, assessment and plan are encrypted at rest by the model; reading them
 * back is Tier 3 and is gated and logged by the caller through
 * `ResolveMedicalTierAction`. A nurse is recorded by staff id, any other practitioner
 * by name (they are usually visiting); the consultation cannot be in the future, a
 * follow-up cannot precede it, and an admission it refers to must be this learner's.
 */
final class RecordConsultationAction extends Action
{
    public const array TYPES = ['walk_in', 'scheduled', 'admission_review', 'follow_up'];

    public const array PRACTITIONERS = ['nurse', 'visiting_doctor', 'physiotherapist', 'dentist'];

    public function execute(RecordConsultationData $data): Consultation
    {
        $errors = [];

        if (! in_array($data->consultationType, self::TYPES, true)) {
            $errors['consultationType'] = 'Choose a valid consultation type.';
        }

        if (! in_array($data->practitionerType, self::PRACTITIONERS, true)) {
            $errors['practitionerType'] = 'Choose a valid practitioner type.';
        }

        if (trim($data->presentingComplaint) === '') {
            $errors['presentingComplaint'] = 'The presenting complaint is required.';
        }

        if ($data->consultedAt->isFuture()) {
            $errors['consultedAt'] = 'A consultation cannot be recorded in the future.';
        }

        if ($data->followUpOn !== null && $data->followUpOn->toDateString() < $data->consultedAt->toDateString()) {
            $errors['followUpOn'] = 'The follow-up cannot be before the consultation.';
        }

        if ($data->practitionerType === 'nurse' && $data->practitionerStaffId === null) {
            $errors['practitionerStaffId'] = 'A nurse consultation is recorded against the nurse\'s staff record.';
        }

        if ($data->practitionerType !== 'nurse' && ($data->externalPractitioner === null || trim($data->externalPractitioner) === '')) {
            $errors['externalPractitioner'] = 'Name the practitioner.';
        }

        if ($data->admissionId !== null && ! SickBayAdmission::query()->where('student_id', $data->studentId)->whereKey($data->admissionId)->exists()) {
            $errors['admissionId'] = 'That sick-bay admission is not this learner\'s.';
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        return $this->transaction(fn (): Consultation => Consultation::create([
            'school_id' => $data->schoolId,
            'student_id' => $data->studentId,
            'admission_id' => $data->admissionId,
            'consulted_at' => $data->consultedAt,
            'consultation_type' => $data->consultationType,
            'presenting_complaint' => $data->presentingComplaint,
            'assessment' => $data->assessment,
            'plan' => $data->plan,
            'practitioner_type' => $data->practitionerType,
            'practitioner_staff_id' => $data->practitionerType === 'nurse' ? $data->practitionerStaffId : null,
            'external_practitioner' => $data->practitionerType === 'nurse' ? null : $data->externalPractitioner,
            'follow_up_on' => $data->followUpOn?->toDateString(),
        ]));
    }
}
