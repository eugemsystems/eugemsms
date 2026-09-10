<?php

declare(strict_types=1);

namespace Modules\Sport\Domain\Actions;

use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Modules\Core\Domain\Actions\Action;
use Modules\Core\Models\Term;
use Modules\Finance\Domain\Actions\CreateAdHocChargeAction;
use Modules\Finance\Domain\DataObjects\CreateAdHocChargeData;
use Modules\People\Models\Student;
use Modules\Sport\Domain\DataObjects\JoinActivityData;
use Modules\Sport\Domain\Exceptions\ActivityCapacityExceededException;
use Modules\Sport\Domain\Exceptions\GuardianConsentRequiredException;
use Modules\Sport\Domain\Exceptions\MedicalClearanceRequiredException;
use Modules\Sport\Models\Activity;
use Modules\Sport\Models\ActivityMembership;
use Modules\Welfare\Models\MedicalCondition;

/**
 * ACT-JoinActivity (Book H2 OPS-07 §3 ⭐/BR-OPS-07-001/002/003/004/
 * AC-OPS-07-001/003). BR-OPS-07-003's fee-rate resolution (what a
 * term of this activity actually costs) is FIN-02's own concern, out
 * of scope here — the caller supplies the resolved full-term rate via
 * `fullTermFeeMinor`; this action's own job is the proration and the
 * real charge, via `CreateAdHocChargeAction` (`FIN-02`'s general
 * "charge a fee component to a student's account" primitive — it
 * produces a pending `AdHocCharge`, not a `LearnerFeeLine` directly;
 * see the owning migration's docblock for why `ad_hoc_charge_id` is
 * this column's real name).
 */
final class JoinActivityAction extends Action
{
    public function __construct(
        private readonly CreateAdHocChargeAction $createAdHocCharge,
    ) {}

    public function execute(JoinActivityData $data): ActivityMembership
    {
        $activity = Activity::findOrFail($data->activityId);
        $student = Student::findOrFail($data->studentId);

        if ($activity->requires_guardian_consent && ! $data->consentReceived) {
            throw GuardianConsentRequiredException::forActivity($activity->id);
        }

        if ($activity->requires_medical_clearance) {
            $hasUnclearedCondition = MedicalCondition::where('school_id', $data->schoolId)
                ->where('student_id', $student->id)
                ->where('status', 'active')
                ->where('affects_physical_activity', true)
                ->exists();

            if ($hasUnclearedCondition && $data->medicalCleared !== true) {
                throw MedicalClearanceRequiredException::forStudent($student->id, $activity->id);
            }
        }

        if ($activity->max_participants !== null && ! $data->overrideCapacity) {
            $currentCount = ActivityMembership::where('school_id', $data->schoolId)
                ->where('activity_id', $activity->id)
                ->where('term_id', $data->termId)
                ->where('status', 'active')
                ->count();

            if ($currentCount >= $activity->max_participants) {
                throw ActivityCapacityExceededException::forActivity($activity->id, $activity->max_participants);
            }
        }

        return $this->transaction(function () use ($activity, $student, $data): ActivityMembership {
            $membership = ActivityMembership::create([
                'school_id' => $data->schoolId,
                'academic_year_id' => $data->academicYearId,
                'term_id' => $data->termId,
                'activity_id' => $activity->id,
                'student_id' => $student->id,
                'role' => $data->role,
                'joined_on' => ($data->joinedOn ?? Carbon::now())->toDateString(),
                'consent_received' => $data->consentReceived,
                'medical_cleared' => $data->medicalCleared,
                'billing_status' => 'pending',
                'status' => 'active',
            ]);

            if ($activity->fee_component_id !== null && $data->fullTermFeeMinor !== null) {
                $proratedAmount = $this->prorate($data->termId, $data->fullTermFeeMinor, $membership->joined_on);

                $charge = $this->createAdHocCharge->execute(new CreateAdHocChargeData(
                    schoolId: $data->schoolId,
                    academicYearId: $data->academicYearId,
                    termId: $data->termId,
                    studentId: $student->id,
                    componentId: $activity->fee_component_id,
                    description: "Activity fee — {$activity->name}",
                    unitRateMinor: $proratedAmount,
                    currency: $data->feeCurrency ?? 'USD',
                    raisedByUserId: $data->raisedByUserId,
                    sourceType: 'activity_membership',
                    sourceId: $membership->id,
                ));

                $membership->update(['ad_hoc_charge_id' => $charge->id, 'billing_status' => 'charged']);
            }

            return $membership;
        });
    }

    private function prorate(int $termId, int $fullTermFeeMinor, CarbonInterface $joinedOn): int
    {
        $term = Term::findOrFail($termId);
        $totalDays = (int) $term->starts_on->diffInDays($term->ends_on) + 1;
        $remainingDays = max(1, (int) $joinedOn->diffInDays($term->ends_on) + 1);

        if ($remainingDays >= $totalDays) {
            return $fullTermFeeMinor;
        }

        return (int) round($fullTermFeeMinor * ($remainingDays / $totalDays));
    }
}
