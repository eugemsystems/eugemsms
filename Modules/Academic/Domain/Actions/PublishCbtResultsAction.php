<?php

declare(strict_types=1);

namespace Modules\Academic\Domain\Actions;

use Modules\Academic\Domain\DataObjects\EnterMarkData;
use Modules\Academic\Domain\DataObjects\PublishCbtResultsData;
use Modules\Academic\Domain\Exceptions\ResultsNotReadyException;
use Modules\Academic\Models\CbtCandidateAttempt;
use Modules\Academic\Models\CbtTest;
use Modules\Core\Domain\Actions\Action;
use Modules\Core\Domain\Exceptions\InvalidStateTransitionException;

/**
 * ACT-PublishCbtResults (Book K ACA-09 §4/BR-ACA-09-008/010 ⭐). A
 * test's final mark is not release-visible to the candidate until
 * every response — auto and manual — is marked. When the test feeds
 * the gradebook, this writes each candidate's final mark through the
 * same `EnterMarkAction` path `ACA-08` uses — one sync mechanism for
 * both, not two.
 */
final class PublishCbtResultsAction extends Action
{
    public function __construct(
        private readonly EnterMarkAction $enterMark,
    ) {}

    public function execute(PublishCbtResultsData $data): CbtTest
    {
        $test = CbtTest::findOrFail($data->testId);

        if ($test->status !== 'closed') {
            throw new InvalidStateTransitionException(
                "A CBT test in [{$test->status}] cannot have its results published.",
                ['test_id' => $test->id, 'status' => $test->status],
            );
        }

        $pendingCount = CbtCandidateAttempt::query()
            ->where('test_id', $test->id)
            ->where('status', 'manual_marking_pending')
            ->count();

        if ($pendingCount > 0) {
            throw ResultsNotReadyException::forTest($test->id, $pendingCount);
        }

        return $this->transaction(function () use ($test, $data): CbtTest {
            if ($test->assessment_id !== null) {
                $finalised = CbtCandidateAttempt::query()
                    ->where('test_id', $test->id)
                    ->whereIn('status', ['auto_marked', 'fully_marked'])
                    ->get();

                foreach ($finalised as $attempt) {
                    $this->enterMark->execute(new EnterMarkData(
                        assessmentId: $test->assessment_id,
                        studentId: $attempt->student_id,
                        enteredByUserId: $data->publishedByUserId,
                        rawMark: (float) $attempt->raw_mark,
                    ));
                }
            }

            $test->update(['status' => 'results_released']);

            return $test->fresh();
        });
    }
}
