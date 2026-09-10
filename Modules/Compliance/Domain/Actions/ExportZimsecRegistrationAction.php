<?php

declare(strict_types=1);

namespace Modules\Compliance\Domain\Actions;

use Modules\Compliance\Domain\DataObjects\ExportZimsecRegistrationData;
use Modules\Compliance\Domain\Exceptions\ZimsecExportBlockedException;
use Modules\Compliance\Models\ZimsecCandidate;
use Modules\Compliance\Models\ZimsecRegistration;
use Modules\Core\Domain\Actions\Action;
use Modules\Core\Domain\Actions\Files\UploadFileAction;
use Modules\Core\Domain\DataObjects\Files\UploadFileData;

/**
 * ACT-ExportZimsecRegistration (Book H3 CMP-01 §1/3 ⭐/BR-CMP-01-004/
 * 013 (AC-CMP-01-001)). ZIMSEC's own Online Candidate Registration
 * System is where a human actually submits — this only prepares and
 * validates, producing the file a school officer uploads there.
 * Refused outright while any candidate still has an `errors`
 * validation status, while the centre number is blank, or while it
 * conflicts with another registration already exported/submitted for
 * this school and exam level. A `warnings`-only registration exports
 * once `acknowledgeWarnings` is explicitly set.
 */
final class ExportZimsecRegistrationAction extends Action
{
    public function __construct(
        private readonly UploadFileAction $uploadFile,
    ) {}

    public function execute(ExportZimsecRegistrationData $data): ZimsecRegistration
    {
        $registration = ZimsecRegistration::findOrFail($data->registrationId);

        $this->assertCentreNumberValid($registration);

        $errorCount = ZimsecCandidate::where('registration_id', $registration->id)->where('validation_status', 'errors')->count();

        if ($errorCount > 0) {
            throw ZimsecExportBlockedException::hasErrors($registration->id, $errorCount);
        }

        $warningCount = ZimsecCandidate::where('registration_id', $registration->id)->where('validation_status', 'warnings')->count();

        if ($warningCount > 0 && ! $data->acknowledgeWarnings) {
            throw ZimsecExportBlockedException::unacknowledgedWarnings($registration->id, $warningCount);
        }

        return $this->transaction(function () use ($registration, $data): ZimsecRegistration {
            $candidates = ZimsecCandidate::where('registration_id', $registration->id)
                ->whereIn('validation_status', ['valid', 'warnings'])
                ->get();

            $payload = [
                'centre_number' => $registration->centre_number,
                'exam_level' => $registration->exam_level,
                'exam_series' => $registration->exam_series,
                'candidates' => $candidates->map(fn (ZimsecCandidate $candidate): array => [
                    'surname' => $candidate->surname,
                    'forenames' => $candidate->forenames,
                    'date_of_birth' => $candidate->date_of_birth->toDateString(),
                    'gender' => $candidate->gender,
                    'national_registration_no' => $candidate->national_registration_no,
                    'subject_entries' => $candidate->subject_entries,
                    'is_repeat_candidate' => $candidate->is_repeat_candidate,
                    'previous_candidate_no' => $candidate->previous_candidate_no,
                    'special_arrangements' => $candidate->special_arrangements,
                ])->values()->all(),
            ];

            $file = $this->uploadFile->execute(new UploadFileData(
                schoolId: $registration->school_id,
                category: 'zimsec_export',
                contents: json_encode($payload, JSON_PRETTY_PRINT) ?: '',
                originalName: "zimsec-{$registration->exam_level}-{$registration->exam_series}.json",
                uploadedByUserId: $data->exportedByUserId,
            ));

            $registration->update(['export_file_id' => $file->id, 'status' => 'exported']);

            return $registration;
        });
    }

    private function assertCentreNumberValid(ZimsecRegistration $registration): void
    {
        if (trim($registration->centre_number) === '') {
            throw ZimsecExportBlockedException::centreNumberMissing($registration->id);
        }

        $conflicting = ZimsecRegistration::where('school_id', $registration->school_id)
            ->where('exam_level', $registration->exam_level)
            ->where('id', '!=', $registration->id)
            ->whereIn('status', ['exported', 'submitted', 'confirmed', 'closed'])
            ->where('centre_number', '!=', $registration->centre_number)
            ->first();

        if ($conflicting !== null) {
            throw ZimsecExportBlockedException::centreNumberInconsistent($registration->id, $registration->centre_number, $conflicting->centre_number);
        }
    }
}
