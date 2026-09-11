<?php

declare(strict_types=1);

namespace Modules\Academic\Domain\Actions;

use Illuminate\Support\Carbon;
use Modules\Academic\Domain\DataObjects\StartAttemptData;
use Modules\Academic\Domain\Exceptions\TestNotOpenException;
use Modules\Academic\Domain\Support\ExtraTimeResolver;
use Modules\Academic\Models\CbtCandidateAttempt;
use Modules\Academic\Models\CbtTest;
use Modules\Core\Domain\Actions\Action;

/**
 * ACT-StartAttempt (Book K ACA-09 §3 ⭐/BR-ACA-09-003/004). Idempotent
 * by design: a second call for the same (test, student) resumes the
 * existing attempt untouched — `seeded_question_order` is never
 * reshuffled, `started_at` never resets, so the candidate's remaining
 * time is exactly the time actually elapsed (AC-ACA-09-001/004).
 */
final class StartAttemptAction extends Action
{
    public function __construct(
        private readonly ExtraTimeResolver $extraTime,
    ) {}

    public function execute(StartAttemptData $data): CbtCandidateAttempt
    {
        $test = CbtTest::findOrFail($data->testId);

        $existing = CbtCandidateAttempt::query()
            ->where('test_id', $test->id)
            ->where('student_id', $data->studentId)
            ->first();

        if ($existing !== null) {
            return $existing;
        }

        if (! in_array($test->status, ['scheduled', 'open'], true) || ! $test->isWithinWindow()) {
            throw TestNotOpenException::forTest($test->id);
        }

        $order = $test->question_ids ?? [];

        if ($test->randomise_question_order) {
            shuffle($order);
        }

        $extraTimeMinutes = $this->extraTime->minutesFor($data->studentId, $test->duration_minutes);

        return $this->transaction(fn (): CbtCandidateAttempt => CbtCandidateAttempt::create([
            'school_id' => $test->school_id,
            'test_id' => $test->id,
            'student_id' => $data->studentId,
            'seeded_question_order' => $order,
            'started_at' => Carbon::now(),
            'extra_time_minutes' => $extraTimeMinutes,
            'tab_switch_count' => 0,
            'status' => 'in_progress',
        ]));
    }
}
