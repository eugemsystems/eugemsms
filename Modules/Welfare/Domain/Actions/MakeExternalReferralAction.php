<?php

declare(strict_types=1);

namespace Modules\Welfare\Domain\Actions;

use Illuminate\Support\Carbon;
use Modules\Core\Domain\Actions\Action;
use Modules\Core\Domain\Actions\Notifications\DispatchNotificationAction;
use Modules\Core\Domain\DataObjects\Notifications\DispatchNotificationData;
use Modules\People\Models\Student;
use Modules\People\Models\StudentGuardian;
use Modules\Welfare\Domain\DataObjects\MakeExternalReferralData;
use Modules\Welfare\Domain\Events\ExternalReferralMade;
use Modules\Welfare\Models\ExternalReferral;
use Throwable;

/**
 * ACT-MakeExternalReferral (Book G BRD-06 §2/BR-BRD-06-017 ⭐).
 * Closes the `hospital` roll-status stub — `OpenRollCallAction`
 * (`BRD-02`) queries `external_referrals.status` against
 * `ExternalReferral::CURRENTLY_AWAY_STATUSES` directly, so no separate
 * push is needed once this row exists.
 */
final class MakeExternalReferralAction extends Action
{
    public function __construct(
        private readonly DispatchNotificationAction $dispatchNotification,
    ) {}

    public function execute(MakeExternalReferralData $data): ExternalReferral
    {
        return $this->transaction(function () use ($data): ExternalReferral {
            $referral = ExternalReferral::create([
                'school_id' => $data->schoolId,
                'student_id' => $data->studentId,
                'admission_id' => $data->admissionId,
                'incident_id' => $data->incidentId,
                'referral_type' => $data->referralType,
                'facility_name' => $data->facilityName,
                'reason' => $data->reason,
                'urgency' => $data->urgency,
                'referred_at' => $data->referredAt,
                'referred_by' => $data->referredByUserId,
                'transport_method' => $data->transportMethod,
                'escort_staff_id' => $data->escortStaffId,
                'guardian_notified_at' => Carbon::now(),
                'guardian_present' => $data->guardianPresent,
                'consent_reference' => $data->consentReference,
                'status' => 'referred',
            ]);

            event(new ExternalReferralMade($referral));

            $this->notifyGuardian($referral);

            return $referral;
        });
    }

    private function notifyGuardian(ExternalReferral $referral): void
    {
        $student = Student::find($referral->student_id);

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
                schoolId: $referral->school_id,
                notificationKey: 'health.external_referral_made',
                recipientType: 'guardian',
                addresses: ['sms' => (string) $guardian->primary_phone, 'email' => (string) $guardian->email],
                context: [
                    'student' => ['first_name' => $student->first_name, 'last_name' => $student->last_name],
                    'facility_name' => $referral->facility_name,
                ],
                recipientId: $guardian->id,
                relatedType: 'external_referral',
                relatedId: $referral->id,
                urgent: $referral->urgency === 'emergency',
            ));
        } catch (Throwable) {
            // Non-blocking.
        }
    }
}
