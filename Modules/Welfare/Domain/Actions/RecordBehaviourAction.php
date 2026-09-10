<?php

declare(strict_types=1);

namespace Modules\Welfare\Domain\Actions;

use Modules\Core\Domain\Actions\Action;
use Modules\Core\Domain\Actions\Notifications\DispatchNotificationAction;
use Modules\Core\Domain\DataObjects\Notifications\DispatchNotificationData;
use Modules\People\Models\Student;
use Modules\People\Models\StudentGuardian;
use Modules\Welfare\Domain\DataObjects\RecordBehaviourData;
use Modules\Welfare\Domain\Events\BehaviourRecorded;
use Modules\Welfare\Domain\Events\PositiveBehaviourRecorded;
use Modules\Welfare\Domain\Events\SafeguardingTriggerDetected;
use Modules\Welfare\Domain\Support\SafeguardingRouter;
use Modules\Welfare\Models\BehaviourCategory;
use Modules\Welfare\Models\BehaviourRecord;
use Throwable;

/**
 * ACT-RecordBehaviour (Book G BRD-07 §2/§3 ⭐/BR-BRD-07-001/018/
 * AC-BRD-07-005). Records both polarities identically — this action
 * has no separate "record misconduct" path, matching BR-BRD-07-001's
 * "the system records commendation as readily as it records
 * misconduct". A `is_safeguarding_trigger` category routes through
 * `SafeguardingRouter`, marks the record confidential, and PAUSES the
 * disciplinary process (`status = 'under_review'`, never `recorded`)
 * — `IssueSanctionAction` refuses to act on a paused record.
 */
final class RecordBehaviourAction extends Action
{
    public function __construct(
        private readonly SafeguardingRouter $safeguardingRouter,
        private readonly DispatchNotificationAction $dispatchNotification,
    ) {}

    public function execute(RecordBehaviourData $data): BehaviourRecord
    {
        $category = BehaviourCategory::findOrFail($data->categoryId);

        return $this->transaction(function () use ($data, $category): BehaviourRecord {
            $record = BehaviourRecord::create([
                'school_id' => $data->schoolId,
                'academic_year_id' => $data->academicYearId,
                'term_id' => $data->termId,
                'student_id' => $data->studentId,
                'category_id' => $category->id,
                'polarity' => $category->polarity,
                'points' => $data->pointsOverride ?? $category->default_points,
                'occurred_at' => $data->occurredAt,
                'location' => $data->location,
                'context' => $data->context,
                'subject_id' => $data->subjectId,
                'class_id' => $data->classId,
                'hostel_id' => $data->hostelId,
                'description' => $data->description,
                'witnesses' => $data->witnesses,
                'other_learners_involved' => $data->otherLearnersInvolved,
                'evidence_file_ids' => $data->evidenceFileIds,
                'reported_by' => $data->reportedByUserId,
                'status' => $category->is_safeguarding_trigger ? 'under_review' : 'recorded',
                'is_confidential' => $category->is_safeguarding_trigger,
            ]);

            if ($category->is_safeguarding_trigger) {
                $caseId = $this->safeguardingRouter->route($record);

                if ($caseId !== null) {
                    $record->update(['safeguarding_case_id' => $caseId]);
                }

                event(new SafeguardingTriggerDetected($record));

                return $record;
            }

            event($category->polarity === 'positive' ? new PositiveBehaviourRecorded($record) : new BehaviourRecorded($record));

            if ($category->auto_notify_guardian) {
                $this->notifyGuardian($record, $category);
            }

            return $record;
        });
    }

    private function notifyGuardian(BehaviourRecord $record, BehaviourCategory $category): void
    {
        $student = Student::find($record->student_id);

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
                schoolId: $record->school_id,
                notificationKey: 'behaviour.record_guardian_notified',
                recipientType: 'guardian',
                addresses: ['sms' => (string) $guardian->primary_phone, 'email' => (string) $guardian->email],
                context: [
                    'student' => ['first_name' => $student->first_name, 'last_name' => $student->last_name],
                    'category' => $category->name,
                    'polarity' => $category->polarity,
                ],
                recipientId: $guardian->id,
                relatedType: 'behaviour_record',
                relatedId: $record->id,
            ));

            $record->update(['guardian_notified_at' => now()]);
        } catch (Throwable) {
            // Non-blocking — the record itself is already durable.
        }
    }
}
