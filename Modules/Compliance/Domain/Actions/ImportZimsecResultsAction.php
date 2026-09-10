<?php

declare(strict_types=1);

namespace Modules\Compliance\Domain\Actions;

use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Modules\Compliance\Domain\DataObjects\ImportZimsecResultsData;
use Modules\Compliance\Domain\DataObjects\ImportZimsecResultsResult;
use Modules\Compliance\Domain\Events\ZimsecResultsImported;
use Modules\Compliance\Models\ZimsecCandidate;
use Modules\Compliance\Models\ZimsecRegistration;
use Modules\Compliance\Models\ZimsecResult;
use Modules\Core\Domain\Actions\Action;
use Modules\People\Models\StudentPriorResult;

/**
 * ACT-ImportZimsecResults (Book H3 CMP-01 §3 ⭐/BR-CMP-01-010/011
 * (AC-CMP-01-005)). Maps `candidate_number` back to this
 * registration's own `zimsec_candidates` — an unmatched candidate
 * number is collected and returned, never silently dropped. Every
 * matched row writes both `zimsec_results` (this module's own record)
 * and `student_prior_results` (Book C, so the result is visible on the
 * learner's transcript) — `is_verified` is always true here since an
 * official ZIMSEC import is itself the verification.
 */
final class ImportZimsecResultsAction extends Action
{
    private const array EXAM_LEVEL_LABELS = [
        'grade_7' => 'ZIMSEC Grade 7',
        'o_level' => 'ZIMSEC O-Level',
        'a_level' => 'ZIMSEC A-Level',
    ];

    public function execute(ImportZimsecResultsData $data): ImportZimsecResultsResult
    {
        $registration = ZimsecRegistration::findOrFail($data->registrationId);
        $examYear = $this->examYear($registration);
        $examinationLabel = self::EXAM_LEVEL_LABELS[$registration->exam_level] ?? "ZIMSEC {$registration->exam_level}";

        return $this->transaction(function () use ($registration, $data, $examYear, $examinationLabel): ImportZimsecResultsResult {
            $imported = new Collection;
            $unmatched = [];

            foreach ($data->rows as $row) {
                $candidate = ZimsecCandidate::where('registration_id', $registration->id)
                    ->where('candidate_number', $row['candidate_number'])
                    ->first();

                if ($candidate === null) {
                    $unmatched[] = [
                        'candidate_number' => $row['candidate_number'],
                        'subject_code' => $row['subject_code'],
                        'reason' => 'No candidate in this registration has this candidate number.',
                    ];

                    continue;
                }

                $result = ZimsecResult::updateOrCreate(
                    [
                        'registration_id' => $registration->id,
                        'student_id' => $candidate->student_id,
                        'subject_code' => $row['subject_code'],
                    ],
                    [
                        'school_id' => $registration->school_id,
                        'candidate_number' => $row['candidate_number'],
                        'subject_name' => $row['subject_name'],
                        'grade' => $row['grade'],
                        'points' => $row['points'] ?? null,
                        'is_provisional' => $row['is_provisional'] ?? false,
                        'imported_at' => Carbon::now(),
                        'imported_by' => $data->importedByUserId,
                        'source_file_id' => $data->sourceFileId,
                    ],
                );

                StudentPriorResult::updateOrCreate(
                    [
                        'school_id' => $registration->school_id,
                        'student_id' => $candidate->student_id,
                        'examination' => $examinationLabel,
                        'exam_year' => $examYear,
                        'subject' => $row['subject_name'],
                    ],
                    [
                        'candidate_number' => $row['candidate_number'],
                        'grade' => $row['grade'],
                        'points' => $row['points'] ?? null,
                        'is_verified' => true,
                    ],
                );

                $imported->push($result);
            }

            $importResult = new ImportZimsecResultsResult($imported, $unmatched);

            event(new ZimsecResultsImported($registration, $importResult));

            return $importResult;
        });
    }

    private function examYear(ZimsecRegistration $registration): int
    {
        if (preg_match('/(\d{4})/', $registration->exam_series, $matches) === 1) {
            return (int) $matches[1];
        }

        return (int) Carbon::now()->year;
    }
}
