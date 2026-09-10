<?php

declare(strict_types=1);

namespace Modules\Sport\Domain\Actions;

use Modules\Core\Domain\Actions\Action;
use Modules\Core\Domain\Actions\Notifications\DispatchNotificationAction;
use Modules\Core\Domain\DataObjects\Notifications\DispatchNotificationData;
use Modules\People\Models\Student;
use Modules\People\Models\StudentGuardian;
use Modules\Sport\Domain\DataObjects\SelectFixtureSquadData;
use Modules\Sport\Domain\Exceptions\MedicalClearanceRequiredException;
use Modules\Sport\Models\Fixture;
use Modules\Welfare\Models\MedicalCondition;
use Throwable;

/**
 * ACT-SelectFixtureSquad (Book H2 OPS-07 §3/BR-OPS-07-002/007/
 * AC-OPS-07-001). Every selected student is checked against
 * `BRD-06`'s `affects_physical_activity` the same way
 * `JoinActivityAction` gates initial membership — an uncleared
 * learner cannot be in the squad at all, not just flagged. Selection
 * then notifies the learner and each guardian with venue/time/
 * requirements (BR-OPS-07-007), non-blocking.
 */
final class SelectFixtureSquadAction extends Action
{
    public function __construct(
        private readonly DispatchNotificationAction $dispatchNotification,
    ) {}

    public function execute(SelectFixtureSquadData $data): Fixture
    {
        $fixture = Fixture::with('team.activity')->findOrFail($data->fixtureId);
        $activity = $fixture->team->activity;

        if ($activity->requires_medical_clearance) {
            foreach ($data->squadStudentIds as $studentId) {
                $hasUnclearedCondition = MedicalCondition::where('school_id', $fixture->school_id)
                    ->where('student_id', $studentId)
                    ->where('status', 'active')
                    ->where('affects_physical_activity', true)
                    ->exists();

                if ($hasUnclearedCondition) {
                    throw MedicalClearanceRequiredException::forStudent($studentId, $activity->id);
                }
            }
        }

        $fixture = $this->transaction(fn (): Fixture => tap($fixture)->update([
            'squad_student_ids' => $data->squadStudentIds,
            'staff_ids' => $data->staffIds === [] ? null : $data->staffIds,
        ]));

        foreach ($data->squadStudentIds as $studentId) {
            $this->notifySelection($fixture, $studentId);
        }

        if ($data->squadStudentIds !== []) {
            $fixture->update(['guardians_notified_at' => now()]);
        }

        return $fixture;
    }

    private function notifySelection(Fixture $fixture, int $studentId): void
    {
        $student = Student::find($studentId);

        if ($student === null) {
            return;
        }

        $context = [
            'student' => ['first_name' => $student->first_name, 'last_name' => $student->last_name],
            'opponent' => $fixture->opponent,
            'fixture_date' => $fixture->fixture_date->toDateString(),
            'venue' => $fixture->venue_name ?? $fixture->venue?->name,
        ];

        $guardianLinks = StudentGuardian::query()
            ->where('student_id', $student->id)
            ->where('status', 'active')
            ->with('guardian')
            ->get();

        foreach ($guardianLinks as $link) {
            if ($link->guardian === null) {
                continue;
            }

            try {
                $this->dispatchNotification->execute(new DispatchNotificationData(
                    schoolId: $fixture->school_id,
                    notificationKey: 'sport.fixture_selection',
                    recipientType: 'guardian',
                    addresses: ['sms' => (string) $link->guardian->primary_phone, 'email' => (string) $link->guardian->email],
                    context: $context,
                    recipientId: $link->guardian->id,
                    relatedType: 'fixture',
                    relatedId: $fixture->id,
                ));
            } catch (Throwable) {
                // Non-blocking — selection is already durable.
            }
        }
    }
}
