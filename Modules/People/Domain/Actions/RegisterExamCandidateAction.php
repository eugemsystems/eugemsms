<?php

declare(strict_types=1);

namespace Modules\People\Domain\Actions;

use InvalidArgumentException;
use Modules\Core\Domain\Actions\Action;
use Modules\People\Domain\DataObjects\RegisterExamCandidateData;
use Modules\People\Models\Application;
use Modules\People\Models\EntranceExam;
use Modules\People\Models\EntranceExamCandidate;

/**
 * ACT-RegisterExamCandidate (Book C PPL-02 §2). Seats an applicant for an exam:
 * the application must be for the exam's intake and still live, the exam must not
 * have started, and the room must have space. A candidate number and seat are
 * assigned in order.
 */
final class RegisterExamCandidateAction extends Action
{
    public function execute(RegisterExamCandidateData $data): EntranceExamCandidate
    {
        return $this->transaction(function () use ($data): EntranceExamCandidate {
            $exam = EntranceExam::query()->lockForUpdate()->findOrFail($data->examId);
            $application = Application::findOrFail($data->applicationId);

            if ($exam->status !== 'scheduled') {
                throw new InvalidArgumentException('Candidates can only be added before the exam starts.');
            }

            if ($application->intake_id !== $exam->intake_id) {
                throw new InvalidArgumentException('That application is not for this exam\'s intake.');
            }

            if (! in_array($application->status, ['submitted', 'under_review', 'exam_completed', 'interview_completed', 'waitlisted'], true)) {
                throw new InvalidArgumentException('That application is not at a stage that can sit the exam.');
            }

            if (EntranceExamCandidate::query()->where('exam_id', $exam->id)->where('application_id', $application->id)->exists()) {
                throw new InvalidArgumentException('That applicant is already registered for this exam.');
            }

            $count = EntranceExamCandidate::query()->where('exam_id', $exam->id)->count();

            if ($exam->capacity !== null && $count >= $exam->capacity) {
                throw new InvalidArgumentException('This exam is full.');
            }

            $sequence = $count + 1;

            return EntranceExamCandidate::create([
                'school_id' => $exam->school_id,
                'exam_id' => $exam->id,
                'application_id' => $application->id,
                'candidate_number' => sprintf('EX%d-%04d', $exam->id, $sequence),
                'seat_number' => (string) $sequence,
            ]);
        });
    }
}
