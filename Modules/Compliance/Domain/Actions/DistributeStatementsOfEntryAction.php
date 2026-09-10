<?php

declare(strict_types=1);

namespace Modules\Compliance\Domain\Actions;

use Illuminate\Support\Collection;
use Modules\Compliance\Models\ZimsecCandidate;
use Modules\Compliance\Models\ZimsecRegistration;
use Modules\Core\Domain\Actions\Action;
use Modules\Core\Domain\Actions\Files\UploadFileAction;
use Modules\Core\Domain\Actions\Notifications\DispatchNotificationAction;
use Modules\Core\Domain\DataObjects\Files\UploadFileData;
use Modules\Core\Domain\DataObjects\Notifications\DispatchNotificationData;
use Modules\Core\Models\File;
use Modules\People\Models\Student;
use Modules\People\Models\StudentGuardian;
use Throwable;

/**
 * ACT-DistributeStatementsOfEntry (Book H3 CMP-01 §3/BR-CMP-01-009).
 * One statement document per candidate, uploaded once and never
 * regenerated; the guardian's own confirmation of receipt is a
 * separate step (`ConfirmStatementOfEntryAction`), not assumed here.
 * Notification failure never blocks the distribution itself — the
 * document is already durable — matching this codebase's established
 * "notifications never block the operation they attach to" doctrine.
 */
final class DistributeStatementsOfEntryAction extends Action
{
    public function __construct(
        private readonly UploadFileAction $uploadFile,
        private readonly DispatchNotificationAction $dispatchNotification,
    ) {}

    /**
     * @return Collection<int, ZimsecCandidate>
     */
    public function execute(int $registrationId): Collection
    {
        $registration = ZimsecRegistration::findOrFail($registrationId);

        $candidates = ZimsecCandidate::where('registration_id', $registration->id)
            ->whereNull('statement_of_entry_id')
            ->whereIn('validation_status', ['valid', 'warnings'])
            ->get();

        $distributed = new Collection;

        foreach ($candidates as $candidate) {
            $distributed->push($this->distributeOne($registration, $candidate));
        }

        return $distributed;
    }

    private function distributeOne(ZimsecRegistration $registration, ZimsecCandidate $candidate): ZimsecCandidate
    {
        $file = $this->transaction(function () use ($registration, $candidate): File {
            $file = $this->uploadFile->execute(new UploadFileData(
                schoolId: $registration->school_id,
                category: 'zimsec_statement_of_entry',
                contents: json_encode([
                    'centre_number' => $registration->centre_number,
                    'exam_level' => $registration->exam_level,
                    'exam_series' => $registration->exam_series,
                    'candidate' => [
                        'surname' => $candidate->surname,
                        'forenames' => $candidate->forenames,
                        'candidate_number' => $candidate->candidate_number,
                        'subject_entries' => $candidate->subject_entries,
                    ],
                ], JSON_PRETTY_PRINT) ?: '',
                originalName: "statement-of-entry-{$candidate->id}.json",
                uploadedByUserId: $registration->submitted_by ?? $registration->school_id,
                attachableType: ZimsecCandidate::class,
                attachableId: $candidate->id,
            ));

            $candidate->update(['statement_of_entry_id' => $file->id]);

            return $file;
        });

        $this->notifyGuardian($registration, $candidate);

        return $candidate->fresh();
    }

    private function notifyGuardian(ZimsecRegistration $registration, ZimsecCandidate $candidate): void
    {
        $student = Student::find($candidate->student_id);

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

        try {
            $this->dispatchNotification->execute(new DispatchNotificationData(
                schoolId: $registration->school_id,
                notificationKey: 'zimsec.statement_of_entry',
                recipientType: 'guardian',
                addresses: ['sms' => (string) $link->guardian->primary_phone, 'email' => (string) $link->guardian->email],
                context: [
                    'student' => ['first_name' => $student->first_name, 'last_name' => $student->last_name],
                    'exam_level' => $registration->exam_level,
                    'exam_series' => $registration->exam_series,
                ],
                recipientId: $link->guardian->id,
                relatedType: 'zimsec_candidate',
                relatedId: $candidate->id,
            ));
        } catch (Throwable) {
            // Non-blocking — the statement document is already durable.
        }
    }
}
