<?php

declare(strict_types=1);

namespace Modules\Welfare\Domain\Actions;

use Illuminate\Validation\ValidationException;
use Modules\Boarding\Domain\Actions\RecordDietaryRequirementAction;
use Modules\Boarding\Domain\DataObjects\RecordDietaryRequirementData;
use Modules\Core\Domain\Actions\Action;
use Modules\People\Models\Student;
use Modules\Welfare\Domain\DataObjects\DeclareMedicalConditionData;
use Modules\Welfare\Domain\Events\LifeThreateningConditionFlagged;
use Modules\Welfare\Models\MedicalCondition;

/**
 * ACT-DeclareMedicalCondition (Book G BRD-06 §2/§4/BR-BRD-06-005/008/
 * 009). `verified_by_nurse` starts false unless a nurse or doctor is
 * doing the declaring — but BR-BRD-06-005 is explicit that Tier 1/2
 * alerts fire regardless: a school does not wait for verification
 * before taking an allergy seriously. `affects_dietary` feeds `BRD-04`
 * with `public_summary` ONLY, never `name`/`diagnosis_notes`
 * (BR-BRD-06-008); `affects_accommodation` needs no push — `BRD-01`
 * pulls the boolean live via `AccommodationConstraintResolver`
 * (BR-BRD-06-009).
 */
final class DeclareMedicalConditionAction extends Action
{
    public function __construct(
        private readonly RecordDietaryRequirementAction $recordDietaryRequirement,
    ) {}

    public function execute(DeclareMedicalConditionData $data): MedicalCondition
    {
        if ($data->affectsDietary && $data->publicSummary === null) {
            throw ValidationException::withMessages([
                'publicSummary' => 'A dietary-affecting condition requires a public summary to hand to BRD-04 — never the clinical detail.',
            ]);
        }

        return $this->transaction(function () use ($data): MedicalCondition {
            $condition = MedicalCondition::create([
                'school_id' => $data->schoolId,
                'student_id' => $data->studentId,
                'condition_type' => $data->conditionType,
                'category' => $data->category,
                'name' => $data->name,
                'severity' => $data->severity,
                'public_summary' => $data->publicSummary,
                'requires_emergency_plan' => $data->requiresEmergencyPlan,
                'affects_dietary' => $data->affectsDietary,
                'affects_physical_activity' => $data->affectsPhysicalActivity,
                'affects_accommodation' => $data->affectsAccommodation,
                'accommodation_requirement' => $data->accommodationRequirement,
                'diagnosis_notes' => $data->diagnosisNotes,
                'diagnosed_on' => $data->diagnosedOn?->toDateString(),
                'diagnosed_by' => $data->diagnosedBy,
                'supporting_document_id' => $data->supportingDocumentId,
                'verified_by_nurse' => in_array($data->declaredBy, ['nurse', 'doctor'], true),
                'status' => 'active',
                'declared_by' => $data->declaredBy,
                'effective_from' => $data->effectiveFrom->toDateString(),
                'created_by' => $data->createdByUserId,
            ]);

            $this->updateStudentFlags($data);

            if ($data->affectsDietary) {
                $this->recordDietaryRequirement->execute(new RecordDietaryRequirementData(
                    schoolId: $data->schoolId,
                    studentId: $data->studentId,
                    requirementType: $data->conditionType === 'allergy' ? 'allergy' : 'medical',
                    severity: $data->severity,
                    description: (string) $data->publicSummary,
                    effectiveFrom: $data->effectiveFrom,
                    requiresEpipen: $data->category === 'anaphylaxis',
                    medicalSourceId: $condition->id,
                ));
            }

            if (in_array($data->severity, MedicalCondition::LIFE_THREATENING_SEVERITIES, true)) {
                event(new LifeThreateningConditionFlagged($condition));
            }

            return $condition;
        });
    }

    private function updateStudentFlags(DeclareMedicalConditionData $data): void
    {
        $student = Student::findOrFail($data->studentId);

        $updates = ['has_medical_alert' => true];

        if ($data->conditionType === 'allergy') {
            $updates['has_allergy_alert'] = true;
        }

        if ($data->affectsDietary) {
            $updates['has_dietary_requirement'] = true;
        }

        $student->update($updates);
    }
}
