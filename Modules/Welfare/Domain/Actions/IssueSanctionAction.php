<?php

declare(strict_types=1);

namespace Modules\Welfare\Domain\Actions;

use Illuminate\Validation\ValidationException;
use Modules\Core\Domain\Actions\Action;
use Modules\Core\Domain\Actions\Notifications\DispatchNotificationAction;
use Modules\Core\Domain\DataObjects\Notifications\DispatchNotificationData;
use Modules\Core\Domain\Support\Settings\ScopeChain;
use Modules\Core\Domain\Support\Settings\SettingResolver;
use Modules\People\Models\Student;
use Modules\People\Models\StudentGuardian;
use Modules\Welfare\Domain\DataObjects\IssueSanctionData;
use Modules\Welfare\Domain\Events\SanctionActive;
use Modules\Welfare\Domain\Events\SanctionProposed;
use Modules\Welfare\Models\BehaviourRecord;
use Modules\Welfare\Models\DisciplinaryCommittee;
use Modules\Welfare\Models\Sanction;
use Modules\Welfare\Models\SanctionType;
use Throwable;

/**
 * ACT-IssueSanction (Book G BRD-07 §3/§4 ⭐/BR-BRD-07-004/005/006/007/
 * 010/013/014/018/AC-BRD-07-002/005/007). A named person issues every
 * sanction — `issuedByUserId` is required by the DTO's own type, never
 * inferred. Refuses outright if any referenced behaviour record is
 * still `under_review` (paused for safeguarding, §3) — no sanction can
 * be issued until safeguarding releases it back. Suspension/exclusion
 * (`requires_committee`) always needs a committee record with the
 * learner's own statement, or an explicit note they declined. A
 * boarder sanctioned with `removes_from_campus` needs supervision/
 * transport arrangements recorded BEFORE the sanction takes effect
 * (BR-BRD-07-014) — this action refuses to create the row without
 * them, rather than creating it and hoping someone remembers.
 */
final class IssueSanctionAction extends Action
{
    public function __construct(
        private readonly SettingResolver $settings,
        private readonly DispatchNotificationAction $dispatchNotification,
    ) {}

    public function execute(IssueSanctionData $data): Sanction
    {
        $sanctionType = SanctionType::findOrFail($data->sanctionTypeId);
        $student = Student::findOrFail($data->studentId);

        $pausedRecordIds = BehaviourRecord::query()
            ->whereIn('id', $data->behaviourRecordIds)
            ->where('status', 'under_review')
            ->pluck('id');

        if ($pausedRecordIds->isNotEmpty()) {
            throw ValidationException::withMessages([
                'behaviourRecordIds' => 'Records '.$pausedRecordIds->implode(', ').' are paused pending safeguarding review (BR-BRD-07-018) — no sanction can be issued until they are released back.',
            ]);
        }

        if ($sanctionType->requires_committee) {
            if ($data->committeeRecordId === null) {
                throw ValidationException::withMessages([
                    'committeeRecordId' => "Sanction type {$sanctionType->name} requires a disciplinary committee record.",
                ]);
            }

            $committee = DisciplinaryCommittee::findOrFail($data->committeeRecordId);

            if (! $committee->hasLearnerAccount()) {
                throw ValidationException::withMessages([
                    'committeeRecordId' => "Committee #{$committee->id} must contain the learner's statement, or an explicit note that they declined.",
                ]);
            }
        }

        $isBoarder = in_array($student->residency, ['BOARDER', 'WEEKLY_BOARDER'], true);

        if ($isBoarder && $sanctionType->removes_from_campus && ($data->boardingArrangements === null || trim($data->boardingArrangements) === '')) {
            throw ValidationException::withMessages([
                'boardingArrangements' => "A boarder's supervision/transport-home arrangements must be recorded before this sanction takes effect (BR-BRD-07-014).",
            ]);
        }

        $scope = new ScopeChain(schoolId: $data->schoolId);
        $approvalThreshold = (int) $this->settings->get('behaviour.sanction_approval_from_severity', $scope);
        $requiresApproval = $sanctionType->severity_level >= $approvalThreshold || $sanctionType->requires_committee;

        return $this->transaction(function () use ($data, $sanctionType, $requiresApproval): Sanction {
            $sanction = Sanction::create([
                'school_id' => $data->schoolId,
                'academic_year_id' => $data->academicYearId,
                'term_id' => $data->termId,
                'student_id' => $data->studentId,
                'sanction_type_id' => $sanctionType->id,
                'behaviour_record_ids' => $data->behaviourRecordIds,
                'reason' => $data->reason,
                'starts_on' => $data->startsOn->toDateString(),
                'ends_on' => $data->endsOn?->toDateString(),
                'duration_days' => $data->durationDays,
                'status' => $requiresApproval ? 'pending_approval' : 'active',
                'issued_by' => $data->issuedByUserId,
                'committee_record_id' => $data->committeeRecordId,
                'boarding_arrangements' => $data->boardingArrangements,
            ]);

            event($requiresApproval ? new SanctionProposed($sanction) : new SanctionActive($sanction));

            $this->notifyGuardian($sanction, $sanctionType);

            return $sanction;
        });
    }

    private function notifyGuardian(Sanction $sanction, SanctionType $sanctionType): void
    {
        $student = Student::find($sanction->student_id);

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
                schoolId: $sanction->school_id,
                notificationKey: 'behaviour.sanction_issued',
                recipientType: 'guardian',
                addresses: ['sms' => (string) $guardian->primary_phone, 'email' => (string) $guardian->email],
                context: [
                    'student' => ['first_name' => $student->first_name, 'last_name' => $student->last_name],
                    'sanction_type' => $sanctionType->name,
                ],
                recipientId: $guardian->id,
                relatedType: 'sanction',
                relatedId: $sanction->id,
                urgent: $sanctionType->removes_from_campus,
            ));

            $sanction->update(['guardian_notified_at' => now()]);
        } catch (Throwable) {
            // BR-BRD-07-007 — notification is unconditional in intent,
            // but a dispatch failure never blocks the sanction itself.
        }
    }
}
