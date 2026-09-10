<?php

declare(strict_types=1);

namespace Modules\Boarding\Domain\Actions;

use Illuminate\Support\Carbon;
use Modules\Boarding\Domain\DataObjects\RecordDepartureData;
use Modules\Boarding\Domain\Events\CollectionRefused;
use Modules\Boarding\Domain\Events\CourtRestrictionAttempt;
use Modules\Boarding\Domain\Events\LearnerDeparted;
use Modules\Boarding\Domain\Support\CollectionAuthorityChecker;
use Modules\Boarding\Models\CollectionAttempt;
use Modules\Boarding\Models\Exeat;
use Modules\Core\Domain\Actions\Action;
use Modules\People\Models\Student;

/**
 * ACT-RecordDeparture (Book F BRD-03 §3/§4 ⭐⭐/BR-BRD-03-010/011/012/
 * 013/AC-BRD-03-003/004/005/007). The collection authority check runs
 * on every single call — no fast path, no trusted-parent bypass — and
 * every path through it, release or refusal, writes a `collection_attempts`
 * row before this action returns. `departure_verified_by` records the
 * name of whoever actually appeared, never the name on the original
 * request (BR-BRD-03-010).
 */
final class RecordDepartureAction extends Action
{
    public function __construct(
        private readonly CollectionAuthorityChecker $checker,
    ) {}

    public function execute(RecordDepartureData $data): CollectionAttempt
    {
        $exeat = Exeat::findOrFail($data->exeatId);
        $student = Student::findOrFail($exeat->student_id);

        $decision = $this->checker->check($student, $data->claim, $exeat, $data->requiresPhotoId);

        return $this->transaction(function () use ($exeat, $student, $data, $decision): CollectionAttempt {
            $attempt = CollectionAttempt::create([
                'school_id' => $student->school_id,
                'student_id' => $student->id,
                'exeat_id' => $exeat->id,
                'attempted_by_guardian_id' => $data->claim->guardianId,
                'attempted_by_name' => $data->claim->name,
                'attempted_by_id_no' => $data->claim->idNo,
                'claimed_relationship' => $data->claim->claimedRelationship,
                'outcome' => $decision->released ? 'released' : ($decision->escalate ? 'escalated' : 'refused'),
                'refusal_reason' => $decision->refusalReason,
                'verified_by_photo' => $data->claim->identityVerified,
                'gate_staff_id' => $data->gateStaffUserId,
                'occurred_at' => Carbon::now(),
            ]);

            if ($decision->released) {
                $exeat->update([
                    'status' => 'departed',
                    'actual_departure_at' => Carbon::now(),
                    'departure_recorded_by' => $data->gateStaffUserId,
                    'departure_verified_by' => $data->claim->name,
                ]);

                event(new LearnerDeparted($exeat));

                return $attempt;
            }

            event(new CollectionRefused($attempt));

            if ($decision->refusalReason === 'court_restriction') {
                event(new CourtRestrictionAttempt($attempt));
            }

            return $attempt;
        });
    }
}
